<?php

namespace App\Http\Controllers\Cooperative\User;

use App\Http\Controllers\Controller;
use App\Models\PickUpOrder;
use App\Models\ProductItem;
use App\Models\ProductOrder;
use App\Models\OrganizationHours;
use App\Models\Organization;
use App\Models\PgngOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Carbon;

class UserCooperativeController extends Controller
{
    public function index()
    {
        $role_id = DB::table('type_organizations')->where('nama','Koperasi')->first()->id;
        $userID = Auth::id();

        $stdID = DB::table('organization_user as ou')
                    ->join('organization_user_student as ous', 'ou.id', '=', 'ous.organization_user_id')
                    ->where('ou.user_id', $userID)
                    ->select('ous.student_id')
                    ->get();

        $sID = array();
        foreach($stdID as $row)
        {
            $sID[] += $row->student_id;
        }

        $orgID = DB::table('class_student as cs')
                    ->join('class_organization as co', 'cs.organclass_id', '=', 'co.id')
                    ->join('organizations as o', 'co.organization_id', '=', 'o.id')
                    ->whereIn('cs.student_id', $sID)
                    ->select('o.id', 'o.nama')
                    ->distinct()
                    ->get();
        
        $koperasi = Organization::where('type_org', $role_id)->select('id', 'nama', 'parent_org')->get();

        return view('koperasi.index', compact('koperasi', 'orgID'));
    }

    public function fetchKoop(Request $request)
    {
        $sID = $request->get('sID');
        
        $koop = Organization::where('parent_org', $sID)->select('id', 'nama')->get();

        return response()->json(['success' => $koop]);
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeInCart(Request $request)
    {
        $userID = Auth::id();

        $item = ProductItem::where('id', $request->i_id)->first();
        // Check if quantity request is less or equal to quantity available
        if($request->qty <= $item->quantity_available) // if true
        {
            //dd($request);
            $order = PgngOrder::where([
                ['user_id', $userID],
                ['status', 1],
                ['organization_id', $request->o_id]
            ])->first();
            
            // Check if order already exists
            if($order) // order exists
            {
                $cartExist = ProductOrder::where([
                    ['product_item_id', $request->i_id],
                    ['pgng_order_id', $order->id],
                ])->first();
                
                // $cartExist = DB::table('product_order as po')
                //             ->join('pgng_orders as pg','po.pgng_order_id','=','pg.id')
                //             ->where('pg.status',1)
                //             ->where('po.product_item_id',$request->id)
                //             ->where('pg.id',$order->id)
                //             ->first();
                // If same item exists in cart
                if($cartExist) // if exists (update)
                {
                    if($request->qty > $cartExist->quantity) // request quant more than existing quant
                    {
                        $newQuantity = intval($item->quantity_available - ($request->qty - $cartExist->quantity)); // decrement stock
                    }
                    else if($request->qty < $cartExist->quantity) // request quant less than existing quant
                    {
                        $newQuantity = intval($item->quantity_available + ($cartExist->quantity- $request->qty)); // increment stock
                    }
                    else if($request->qty == $cartExist->quantity) // request quant equal existing quant
                    {
                        $newQuantity = intval((int)$item->quantity_available + 0); // stock not change
                    }

                    ProductOrder::where('id', $cartExist->id)->update([
                        'quantity' => $request->qty
                    ]);
                }
                else // if not exists (insert)
                {
                    ProductOrder::create([
                        'quantity' => $request->qty,
                        'status' => 1,
                        'product_item_id' => $request->i_id,
                        'pgng_order_id' => $order->id
                    ]);

                    $newQuantity = intval((int)$item->quantity_available - (int)$request->qty);
                }


                $cartItem = DB::table('product_order as po')
                                ->join('product_item as pi', 'po.product_item_id', '=', 'pi.id')
                                ->where('po.pgng_order_id', $order->id)
                                ->select('po.quantity', 'pi.price')
                                ->get();
                
                $newTotalPrice = 0;
                
                foreach($cartItem as $row)
                {
                    $newTotalPrice += doubleval($row->price * $row->quantity);
                }

                PgngOrder::where([
                    ['user_id', $userID],
                    ['status', 1],
                    ['organization_id', $request->o_id]
                ])
                ->update([
                    'total_price' => $newTotalPrice
                ]);
            }
            else // order did not exists
            {
                $totalPrice = $item->price * (int)$request->qty;

                $newQuantity = intval((int)$item->quantity_available - (int)$request->qty);

                $newOrder = PgngOrder::create([
                    'method_status' => 1,
                    'total_price' => $totalPrice,
                    'status' => 1,
                    'user_id' => $userID,
                    'organization_id' => $request->o_id
                ]);

                ProductOrder::create([
                    'quantity' => $request->qty,
                    'status' => 1,
                    'product_item_id' => $request->i_id,
                    'pgng_order_id' => $newOrder->id
                ]);
            }

            // check if quantity is 0 after add to cart
            if($newQuantity != 0) // if not 0
            {
                ProductItem::where('id', $request->i_id)->update(['quantity_available' => $newQuantity]);
            }
            else // if 0 (change item status)
            {
                ProductItem::where('id', $request->i_id)
                ->update(['quantity_available' => $newQuantity, 'status' => 0]);
            }
            
            return back()->with('success', 'Item Berjaya Dikemaskini!');
        }
        else // if false
        {
            $message = "Stock Barang Ini Tidak Mencukupi | Stock : ".$item->quantity_available;
            return back()->with('error', $message);
        }
        // dd($request->all());
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $todayDate = Carbon::now()->format('l');

        $day = $this->getDayIntegerByDayName($todayDate);

        $koperasi = DB::table('organizations as o')
                    ->join('organization_hours as oh', 'o.id', '=', 'oh.organization_id')
                    ->where('o.id', $id)
                    ->where('oh.day', $day)
                    ->select('o.id', 'o.nama', 'o.telno', 'o.address', 'o.city', 'o.postcode', 'o.state', 'o.parent_org',
                            'oh.day', 'oh.open_hour', 'oh.close_hour', 'oh.status')
                    ->first();
        dd($koperasi,$id);
        $org = Organization::where('id', $koperasi->parent_org)->select('nama')->first();
        
        $product_item = DB::table('product_item as pi')
                        ->join('product_group as pt', 'pi.product_group_id', '=', 'pt.id')
                        ->where('pt.organization_id', $koperasi->id)
                        ->select('pi.*', 'pt.name as type_name')
                        ->orderBy('pi.name')
                        ->get();
        
        $product_type = DB::table('product_group as pt')
                            ->join('product_item as pi', 'pt.id', '=', 'pi.product_group_id')
                            ->select('pt.id as type_id', 'pt.name as type_name')
                            ->where('pt.organization_id', $koperasi->id)
                            ->distinct()
                            ->get();

        $k_open_hour = date('h:i A', strtotime($koperasi->open_hour));
        
        $k_close_hour = date('h:i A', strtotime($koperasi->close_hour));

        return view('koperasi.menu', compact('koperasi', 'org', 'product_item', 'product_type', 'k_open_hour', 'k_close_hour'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $cart_item = array(); // empty if cart is empty
        $user_id = Auth::id();

        $cart = PgngOrder::where([
            ['status', 1],
            ['organization_id', $id],
            ['user_id', $user_id],
        ])->first();
        
        if($cart)
        {
            // $cart_item = DB::table('product_order as po')
            //         ->join('product_item as pi', 'po.product_item_id', '=', 'pi.id')
            //         ->where('po.status', 1)
            //         ->where('po.pgng_order_id', $cart->id)
            //         ->select('po.id', 'po.quantity', 'pi.name', 'pi.price', 'pi.image')
            //         ->get();
            $cart_item = DB::table('product_order as po')
            ->join('product_item as pi', 'po.product_item_id', '=', 'pi.id')
            ->join('pgng_orders as pg','po.pgng_order_id','=','pg.id')
            ->where('pg.status', 1)
            ->where('po.pgng_order_id', $cart->id)
            ->select('pg.id', 'po.quantity', 'pi.name', 'pi.price', 'pi.image')
            ->get();

            // $tomorrowDate = Carbon::tomorrow()->format('Y-m-d');

            $allDay = OrganizationHours::where([
                ['organization_id', $id],
                ['status', 1],
            ])->get();
            
            $isPast = array();
            
            foreach($allDay as $row)
            {
                $TodayDate = Carbon::now()->format('l');
                // $MondayNextWeek = Carbon::now()->next(1);

                $day = $this->getDayIntegerByDayName($TodayDate);

                $key = strval($row->day);
                
                $isPast = $this->getDayStatus($day, $row->day, $isPast, $key);
            }
            return view('koperasi.cart', compact('cart', 'cart_item', 'allDay', 'isPast' ,'id'));
        }
        else
        {
            return view('koperasi.cart', compact('cart', 'cart_item' , 'id'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $org = PgngOrder::where('id', $id)->select('organization_id as id')->first();

        $daySelect = (int)$request->week_status;
                
        $pickUp = Carbon::now()->next($daySelect)->toDateString();
        
        PgngOrder::where([
            ['status', 1],
            ['id', $id],
        ])->update([
            'pickup_date' => $pickUp,
            'note' => $request->note,
            'status' => 2,
        ]);

        // ProductOrder::where([
        //     ['status', 1],
        //     ['pickup_order_id', $id],
        // ])->update(['status' => 2]);

        return redirect('/koperasi/koop/'.$org->id)->with('success', 'Pesanan Anda Berjaya Direkod!');
    }

    public function destroyItemCart($org_id, $id)
    {
        $userID = Auth::id();

        // $cart_item = ProductOrder::where('pgng_order_id', $id);
        $cart_item = ProductOrder::where('pgng_order_id', $id);

        $item = $cart_item->first();

        // $product_item = ProductOrder::where('product_item_id', $item->product_item_id);
        $product_item = ProductItem::where('id', $item->product_item_id);

        $product_item_quantity = $product_item->first();

        $newQuantity = intval($product_item_quantity->quantity_available + $item->quantity); // increment quantity

        /* If previous product item is being unavailable because of added item in cart,
           after the item deleted, the quantity in product_item will increment back and
           the item will be available */
        if($product_item_quantity->quantity_available == 0)
        {
            $product_item->update([
                'quantity_available' => $newQuantity,
                'status' => 1,
            ]);
        }
        else
        {
            $product_item->update([
                'quantity_available' => $newQuantity,
            ]);
        }

        $cart_item->forceDelete();

        $allCartItem = DB::table('product_order as po')
                        ->join('product_item as pi', 'po.product_item_id', '=', 'pi.id')
                        ->join('pgng_orders as pg','po.pgng_order_id','=','pg.id')
                        ->where('pg.id', $item->pgng_order_id)
                        ->where('pg.status', 1)
                        ->select('po.quantity', 'pi.price')
                        ->get();
        
        // If cart is not empty
        if(count($allCartItem) != 0)
        {

            $newTotalPrice = 0;
            
            // Recalculate total
            foreach($allCartItem as $row)
            {
                $newTotalPrice += doubleval($row->price * $row->quantity);
            }

            PgngOrder::where([
                ['user_id', $userID],
                ['status', 1],
                ['organization_id', $org_id],
            ])->update(['total_price' => $newTotalPrice]);
        }
        else // If cart is empty (delete order)
        {
            PgngOrder::where([
                ['user_id', $userID],
                ['status', 1],
                ['organization_id', $org_id],
            ])->forceDelete();
        }
        

        return back()->with('success', 'Item Berjaya Dibuang');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        
    }

    public function indexOrder()
    {
        $type_id = DB::table('type_organizations')->where('nama','Koperasi')->first()->id;
        $userID = Auth::id();

        $query = DB::table('pgng_orders as ko')
                ->join('organizations as o', 'ko.organization_id', '=', 'o.id')
                ->whereIn('status', [2,4])
                ->where('user_id', $userID)
                ->where('type_org', $type_id)
                ->where('ko.deleted_at', null)
                ->select('ko.*', 'o.nama as koop_name', 'o.telno as koop_telno')
                ->orderBy('ko.status', 'desc')
                ->orderBy('ko.pickup_date', 'asc')
                ->orderBy('ko.updated_at', 'desc');

        $all_order = $query->get();

        $allPickUpDate = array();
        $isPast = array();

        foreach($all_order as $row)
        {
            $allPickUpDate[] += date(strtotime($row->pickup_date));

            $key = date("Y-m-d", date(strtotime($row->pickup_date)));

            $pickUpDate = Carbon::parse($row->pickup_date)->startofDay();
            $todayDate = Carbon::now()->startOfDay();

            // Check if today is the day of the pickup day or is still not yet arrived
            if($todayDate->lte($pickUpDate))
            {
                $isPast[$key] = 0;
            }
            else
            {
                $isPast[$key] = 1;
            }

            if($row->pickup_date == $key && $isPast[$row->pickup_date] == 1)
            {
                // Status changed to overdue
                PgngOrder::where('pickup_date', $row->pickup_date)->update(['status' => 4]);
            }
            else
            {
                // Status is still not picked
                PgngOrder::where('pickup_date', $row->pickup_date)->update(['status' => 2]);
            }
        }

        $order = $query->paginate(5);

        return view('koperasi.order', compact('order'));
    }

    public function indexHistory()
    {
        $role_id = DB::table('type_organizations')->where('nama','Koperasi')->first()->id;
        $userID = Auth::id();

        $query = DB::table('pgng_orders as ko')
                ->join('organizations as o', 'ko.organization_id', '=', 'o.id')
                ->whereIn('status', [3, 100, 200])
                ->where('user_id', $userID)
                ->where('o.type_org', $role_id)
                ->select('ko.*', 'o.nama as koop_name', 'o.telno as koop_telno')
                ->orderBy('ko.status', 'desc')
                ->orderBy('ko.pickup_date', 'asc')
                ->orderBy('ko.updated_at', 'desc');

        $order = $query->paginate(5);

        return view('koperasi.history', compact('order'));
    }

    public function indexList($id)
    {
        $userID = Auth::id();

        // Get Information about the order
        $list_detail = DB::table('pgng_orders as ko')
                        ->join('organizations as o', 'ko.organization_id', '=', 'o.id')
                        ->where('ko.id', $id)
                        ->where('ko.status', '>' , 0)
                        ->where('ko.user_id', $userID)
                        ->select('ko.updated_at', 'ko.pickup_date', 'ko.total_price', 'ko.note', 'ko.status',
                                'o.id','o.nama', 'o.parent_org', 'o.telno', 'o.email', 'o.address', 'o.postcode', 'o.state')
                        ->first();

        $date = Carbon::createFromDate($list_detail->pickup_date); // create date based on pickup date

        $day = $this->getDayIntegerByDayName($date->format('l')); // get day in integer based on day name

        // get open and close hour org
        $allOpenDays = OrganizationHours::where([
            ['organization_id', $list_detail->id],
            ['day', $day],
        ])->first();

        // get parent name
        $parent_org = Organization::where('id', $list_detail->parent_org)->select('nama')->first();

        $sekolah_name = $parent_org->nama;

        // get all product based on order
        $item = DB::table('product_order as po')
                ->join('product_item as pi', 'po.product_item_id', '=', 'pi.id')
                ->join('pgng_orders as pg','po.pgng_order_id','=','pg.id')
                ->where('po.pgng_order_id', $id)
                ->where('pg.status', '>', 0)
                ->select('pi.name', 'pi.price', 'po.quantity')
                ->get();
                // dd($item);

        $totalPrice = array();
        
        foreach($item as $row)
        {
            $key = strval($row->name); // key based on item name
            $totalPrice[$key] = doubleval($row->price * $row->quantity); // calculate total for each item in cart
        }

        return view('koperasi.list', compact('list_detail', 'allOpenDays', 'sekolah_name', 'item', 'totalPrice'));
    }

    public function fetchAvailableDay(Request $request)
    {   
        $order_id = $request->get('oID');

        $order = PgngOrder::where('id', $order_id)->first();

        $allDay = OrganizationHours::where('organization_id', $order->organization_id)->where('status', 1)->get();
        
        $isPast = array();
            
        foreach($allDay as $row)
        {
            $TodayDate = Carbon::now()->format('l');

            $day = $this->getDayIntegerByDayName($TodayDate);

            $key = strval($row->day);

            $isPast = $this->getDayStatus($day, $row->day, $isPast, $key);

        }
        return response()->json(['day' => $allDay, 'past' => $isPast ]);
    }

    public function updatePickUpDate(Request $request)
    {
        $order_id = $request->get('oID');
        $daySelect = (int)$request->get('day');

        $pickUp = Carbon::now()->next($daySelect)->toDateString();

        $result = PgngOrder::where('id', $order_id)->update(['pickup_date' => $pickUp]);
        
        $this->indexOrder(); // Recall function to recheck status

        if ($result) {
            Session::flash('success', 'Hari Pengambilan Berjaya diubah');
            return View::make('layouts/flash-messages');
        } else {
            Session::flash('error', 'Hari Pengambilan Tidak Berjaya diubah');
            return View::make('layouts/flash-messages');
        }
    }

    public function destroyUserOrder($id)
    {
        $queryKO = PgngOrder::find($id)->update(['status', 'Cancel by user']);
        
        $resultKO = PgngOrder::find($id)->delete();
        
        $resultPO = ProductOrder::where('pgng_order_id', $id)->delete();

        // $this->indexOrder(); // Recall function to recheck status
        
        if ($resultKO && $resultPO) {
            Session::flash('success', 'Pesanan Berjaya Dibuang');
            return View::make('layouts/flash-messages');
        } else {
            Session::flash('error', 'Pesanan Gagal Dibuang');
            return View::make('layouts/flash-messages');
        }
    }

    /*-------------------------- START KOOP SHOP --------------------------*/

    public function indexKoop()
    {
        $role_id = DB::table('type_organizations')->where('nama','Koperasi')->first()->id;
        $sekolah = DB::table('organizations')
                   ->where('type_org',$role_id)
                   ->get();
        return view('koop.index',compact('sekolah'))->with('sekolah',$sekolah);
    }

    public function koopShop(Int $id)
    {
        $role_id = DB::table('type_organizations')->where('nama','Koperasi')->first()->id;
        $Sekolah = DB::table('organizations')
        ->where('type_org',$role_id)
        ->where('id',$id)
        ->get();

        $products = DB::table('product_group as pg')
         ->join('product_item as p','pg.id','p.product_group_id')
         ->select('p.*','pg.id as groupId','pg.name as groupName')
         ->where('pg.organization_id',$id) 
        ->get();                 

        $todayDate = Carbon::now()->format('l');

        $day = $this->getDayIntegerByDayName($todayDate);

        $koperasi = DB::table('organizations as o')
        ->join('organization_hours as oh', 'o.id', '=', 'oh.organization_id')
        ->where('o.id', $id)
        ->where('oh.day', $day)
        ->select('o.id', 'o.nama', 'o.telno', 'o.address', 'o.city', 'o.postcode', 'o.state', 'o.parent_org',
                'oh.day', 'oh.open_hour', 'oh.close_hour', 'oh.status')
        ->first();

        $childrenByParent = DB::table('users')
        ->join('organization_user as ou', 'ou.user_id', '=', 'users.id')
        ->join('organization_user_student as ous','ou.id','=','ous.organization_user_id')
        ->join('students as s','s.id','=','ous.student_id')
        ->join('class_student as cs','cs.student_id','=','s.id')
        ->join('class_organization as co','co.id','=','cs.organclass_id')
        ->join('classes as c','c.id','=','co.class_id')
        ->select('s.*','users.id as parentId','users.name as parentName', 'ou.organization_id','c.nama as className')
        ->where('ou.organization_id', $koperasi->parent_org)
        ->where('ou.role_id', 6)
        ->where('ou.status', 1)
        ->orderBy('c.nama')
        ->get();

        //dd($childrenOfParent);
        $k_open_hour = date('h:i A', strtotime($koperasi->open_hour));
        
        $k_close_hour = date('h:i A', strtotime($koperasi->close_hour));
        //dd($products);
        return view('koop.koop')
        ->with('Sekolah',$Sekolah)
        ->with('products',$products)
        ->with('koperasi',$koperasi)
        ->with('k_open_hour', $k_open_hour)
        ->with('k_close_hour', $k_close_hour)
        ->with('childrenByParent',$childrenByParent);
    }

    public function storeKoop()
    {
        return redirect('koperasi/koop');
    }

    public function koopCart(Int $id)
    {
        $sekolah = DB::table('organizations')
        ->where('type_org',1039)
        ->where('id',$id)
        ->get();

        return view('koop.cart',)->with('sekolah',$sekolah);
    }

    /*-------------------------- END KOOP SHOP --------------------------*/

    public function getDayStatus($todayDay, $allDay, $arr, $key_index)
    {
        // If today is Sunday
        if($todayDay == 0) 
        { $arr[$key_index] = "Minggu Hadapan"; } // Pick up date always next week
        else
        {
            // if array of day available is sunday
            if($allDay == 0) { $arr[$key_index] = "Minggu Ini"; } // Pick up date for Sunday always this week
            // if today day is passed or today
            else if($todayDay >= $allDay) { $arr[$key_index] = "Minggu Hadapan"; } // Pick up date must next week
            // if today day is not passed yet
            else if($todayDay < $allDay) { $arr[$key_index] = "Minggu Ini"; } // Pick up date available this week
        }

        return $arr;
    }

    public function getDayIntegerByDayName($date)
    {
        $day = null;
        if($date == "Monday") { $day = 1; }
        else if($date == "Tuesday") { $day = 2; }
        else if($date == "Wednesday") { $day = 3; }
        else if($date == "Thursday") { $day = 4; }
        else if($date == "Friday") { $day = 5; }
        else if($date == "Saturday") { $day = 6; }
        else if($date == "Sunday") { $day = 0; }
        return $day;
    }

    public function productsListByGroup(Request $request)
    {
        $id=$request->kooperasiId;
        $oid=Organization::where('id', $id)->select('parent_org')->first();
        
        //filter by Tahun
        $selectedTarget=$request->selectedGroup;

        $classExist=DB::table('classes')
        ->join('class_organization', 'class_organization.class_id', '=', 'classes.id')
        ->where('classes.status', 1)
        ->where('classes.nama',$selectedTarget )
        ->where('class_organization.organization_id', $oid)
        ->first();

        if($classExist){
            $products = DB::table('product_group as pg')
            ->join('product_item as p','pg.id','p.product_group_id')
            ->select('p.*','pg.id as groupId','pg.name as groupName')
            ->where('pg.organization_id',$id) 
            ->whereJsonContains('p.target->data', $Tahun)
            ->whereNull('p.deleted_at')
            ->whereNull('pg.deleted_at')
           ->get();  
        }
        else if (strpos($selectedTarget, "Tahun") !== false){
            $Tahun = str_replace("Tahun", "", $request->selectedGroup);
            $products = DB::table('product_group as pg')
            ->join('product_item as p','pg.id','p.product_group_id')
            ->select('p.*','pg.id as groupId','pg.name as groupName')
            ->where('pg.organization_id',$id) 
            ->whereJsonContains('p.target->data', $Tahun)
            ->whereNull('p.deleted_at')
            ->whereNull('pg.deleted_at')
           ->get();  
        }
        //get all product for All tahun (no specification of Tahun)
        else if($request->selectedGroup=="GeneralItem")
        {
            $products = DB::table('product_group as pg')
            ->join('product_item as p','pg.id','p.product_group_id')
            ->select('p.*','pg.id as groupId','pg.name as groupName')
            ->where('pg.organization_id',$id)
            ->whereJsonContains('p.target->data', 'All')
            ->whereNull('p.deleted_at')
            ->whereNull('pg.deleted_at')
           ->get();  
        }
        else if($request->selectedGroup=="AllItem")
        {
            $products = DB::table('product_group as pg')
            ->join('product_item as p','pg.id','p.product_group_id')
            ->select('p.*','pg.id as groupId','pg.name as groupName')
            ->where('pg.organization_id',$id)
            ->whereNull('p.deleted_at')
            ->whereNull('pg.deleted_at')
            //->whereJsonContains('p.target->data', 'ALL')
           ->get();  
        }
        //by category
        else{
            $products = DB::table('product_group as pg')
            ->join('product_item as p','pg.id','p.product_group_id')
            ->select('p.*','pg.id as groupId','pg.name as groupName')
            ->where('pg.organization_id',$id) 
            ->where('p.product_group_id',$request->selectedGroup)
            ->whereNull('p.deleted_at')
            ->whereNull('pg.deleted_at')
           ->get();  
        }
        //return response()->json(['status' => "success"]);
        return response()->json(['products' => $products]);
    }

    public function fetchItemToModel(Request $request)
    {
        $i_id = $request->get('i_id');
        $o_id = $request->get('o_id');
        $user_id = Auth::id();
        $modal = '';
        
        $item = ProductItem::where('id', $i_id)
        ->select('id', 'type', 'name', 'price', 'quantity_available as qty', 'selling_quantity as unit_qty')
        ->first();

        $order = DB::table('product_order as po')->join('pgng_orders as pu', 'pu.id', 'po.pgng_order_id')
        ->where([
            ['pu.user_id', $user_id],
            ['pu.organization_id', $o_id],
            ['po.product_item_id', $i_id],
            ['pu.status', 'In cart'],
        ])
        ->select('quantity as qty', 'selling_quantity as unit_qty')
        ->first();
        
        if($order) { // Order exists in cart
            $max_quantity = $item->qty + ($order->qty);
        } else {
            $max_quantity = $item->qty;
        }
        
        $modal .= '<div class="row justify-content-center"><i>Kuantiti Inventori : '.$item->qty.'</i></div>';

        
        if(!$order) {
            $modal .= '<input id="quantity_input" type="text" value="1" name="quantity_input">';
            $modal .= '<div id="quantity-danger">Kuantiti Melebihi Inventori</div>';
        } else {
            $modal .= '<input id="quantity_input" type="text" value="'.$order->qty.'" name="quantity_input">';
            $modal .= '<div id="quantity-danger">Kuantiti Melebihi Inventori</div>';
            $modal .= '<div class="row justify-content-center"><i>Dalam Troli : '.$order->qty * $order->unit_qty.' Unit</i></div>';
        }

        return response()->json(['item' => $item, 'body' => $modal, 'quantity' => $max_quantity]);
    }
}
