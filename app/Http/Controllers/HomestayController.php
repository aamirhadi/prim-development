<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganizationRequest;
use App\Models\Organization;
use App\Models\TypeOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\File;
use App\Http\Jajahan\Jajahan;
use App\Models\OrganizationHours;
use App\Models\Donation;
use Illuminate\Support\Facades\Validator;
use App\Models\OrganizationRole;
use App\Models\Promotion;
use App\Models\Room;
use View;

use DateTime;
use DateInterval;
use DatePeriod;

class HomestayController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $data = Promotion::join('organizations', 'promotions.homestayid', '=', 'organizations.id')
        ->join('organization_user','organizations.id','organization_user.organization_id')
    ->where('organization_user.user_id',$userId)
    ->select('organizations.nama', 'promotions.promotionid','promotions.promotionname','promotions.datefrom', 'promotions.dateto','promotions.discount')
    ->get();

        return view('homestay.listpromotion', compact('data'));
    }

    public function setpromotion()
    {
        $orgtype = 'Homestay / Hotel';
        $userId = Auth::id();
        $data = DB::table('organizations as o')
            ->leftJoin('organization_user as ou', 'o.id', 'ou.organization_id')
            ->leftJoin('type_organizations as to', 'o.type_org', 'to.id')
            ->select("o.*")
            ->distinct()
            ->where('ou.user_id', $userId)
            ->where('to.nama', $orgtype)
            ->where('o.deleted_at', null)
            ->get();
        return view('homestay.setpromotion', compact('data'));
    }

    public function insertpromotion(Request $request)
    {
        
        $userId = Auth::id();
        $request->validate([
            'homestayid' => 'required',
            'promotionname' => 'required',
            'datefrom' => 'required',
            'dateto' => 'required',
            'discount' => 'required'
        ]);

        
            $promotion = new Promotion();
            $promotion->homestayid = $request->homestayid;
            $promotion->promotionname = $request->promotionname;
            $promotion->datefrom = $request->datefrom;
            $promotion->dateto = $request->dateto;
            $promotion->discount = $request->discount;
            $result = $promotion->save();

            if($result)
        {
            return back()->with('success', 'Promosi Berjaya Ditambah');
        }
        else
        {
            return back()->withInput()->with('error', 'Promosi Telahpun Didaftarkan');

        }
    }

    public function disabledatepromo($homestayid)
{
    
    $promotions = Promotion::where('homestayid', $homestayid) // Add your additional condition here
                   ->get();

    $disabledDates = [];

    foreach ($promotions as $promotion) {
        $begin = new DateTime($promotion->datefrom);
        $end = new DateTime($promotion->dateto);
        $interval = new DateInterval('P1D');
        $daterange = new DatePeriod($begin, $interval, $end);

        foreach ($daterange as $date) {
            $disabledDates[] = $date->format('Y-m-d');
        }
    }

    return response()->json(['disabledDates' => $disabledDates]);
}

public function addroom(Request $request)
{
    $userId = Auth::id();
    $request->validate([
        'homestayid' => 'required',
        'roomname' => 'required',
        'roompax' => 'required',
        'details' => 'required',
        'price' => 'required'
    ]);

    $status = 'Available';

    $room = new Room();
    $room->homestayid = $request->homestayid;
    $room->roomname = $request->roomname;
    $room->roompax = $request->roompax;
    $room->details = $request->details;
    $room->price = $request->price;
    $room->status = $status;
    $result = $room->save();

    if($result)
{
    return back()->with('success', 'Bilik Berjaya Ditambah');
}
else
{
    return back()->withInput()->with('error', 'Bilik Telahpun Didaftarkan');

}

    
}

public function editpromo(Request $request,$promotionid)
{
    $request->validate([
        'promotionname' => 'required',
        'datefrom' => 'required',
        'dateto' => 'required',
        'discount' => 'required|numeric|min:1|max:100'
    ]);

    $promotionname = $request->input('promotionname');
    $datefrom = $request->input('datefrom');
    $dateto = $request->input('dateto');
    $discount = $request->input('discount');

    $userId = Auth::id();
    $promotion = Promotion::where('promotionid',$promotionid)
        ->first();

        if ($promotion) {
            $promotion->promotionname = $request->promotionname;
            $promotion->datefrom = $request->datefrom;
            $promotion->dateto = $request->dateto;
            $promotion->discount = $request->discount;

            $result = $promotion->save();

            if($result)
            {
                return back()->with('success', 'Promosi Berjaya Disunting');
            }
            else
            {
                return back()->withInput()->with('error', 'Promosi Gagal Disunting');
    
            }
        } else {
            return back()->with('fail', 'Promotions not found!');
        }
    }

    public function urusbilik()
    {
        $orgtype = 'Homestay / Hotel';
        $userId = Auth::id();
        $data = DB::table('organizations as o')
            ->leftJoin('organization_user as ou', 'o.id', 'ou.organization_id')
            ->leftJoin('type_organizations as to', 'o.type_org', 'to.id')
            ->select("o.*")
            ->distinct()
            ->where('ou.user_id', $userId)
            ->where('to.nama', $orgtype)
            ->where('o.deleted_at', null)
            ->get();

            $rooms = Organization::join('rooms', 'organizations.id', '=', 'rooms.homestayid')
                ->join('organization_user','organizations.id', '=', 'organization_user.organization_id')
                ->where('organization_user.user_id',$userId)
                ->select('organizations.id', 'rooms.roomid','rooms.roomname','rooms.details', 'rooms.roompax','rooms.price','rooms.status')
                ->get();
        return view('homestay.urusbilik', compact('data','rooms'));
    }

    public function gettabledata(Request $request)
    {
        $homestayid = $request->homestayid;
        $userId = Auth::id();

        $rooms = Organization::join('rooms', 'organizations.id', '=', 'rooms.homestayid')
                ->join('organization_user', 'organizations.id', '=', 'organization_user.organization_id')
                ->where('organization_user.user_id', $userId)
                ->where('organizations.id', $homestayid) // Filter by the selected homestay
                ->select('organizations.id', 'rooms.roomid', 'rooms.roomname', 'rooms.details', 'rooms.roompax', 'rooms.price', 'rooms.status')
                ->get();
              
                return response()->json($rooms);
    }

    public function tambahbilik()
    {
        $orgtype = 'Homestay / Hotel';
        $userId = Auth::id();
        $data = DB::table('organizations as o')
            ->leftJoin('organization_user as ou', 'o.id', 'ou.organization_id')
            ->leftJoin('type_organizations as to', 'o.type_org', 'to.id')
            ->select("o.*")
            ->distinct()
            ->where('ou.user_id', $userId)
            ->where('to.nama', $orgtype)
            ->where('o.deleted_at', null)
            ->get();
        return view('homestay.tambahbilik',compact('data'));
    }

    public function bookinglist()
    {
        $orgtype = 'Homestay / Hotel';
        $userId = Auth::id();
        $data = DB::table('organizations as o')
            ->join('organization_user as ou', 'o.id', 'ou.organization_id')
            ->join('type_organizations as to', 'o.type_org', 'to.id')
            ->select("o.*")
            ->selectRaw('(SELECT MIN(price) FROM rooms WHERE homestayid = o.id) AS cheapest')
            ->distinct()
            ->where('to.nama', $orgtype)
            ->where('o.deleted_at', null)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('rooms')
                      ->whereRaw('rooms.homestayid = o.id');
            }) // Add WHERE EXISTS condition
            ->get();
            return view('homestay.bookinglist',compact('data'));
    }

    public function bookhomestay($homestayid)
    {


        $data = Organization::join('rooms', 'organizations.id', '=', 'rooms.homestayid')
                ->join('organization_user', 'organizations.id', '=', 'organization_user.organization_id')
                ->where('organizations.id', $homestayid) // Filter by the selected homestay
                ->select('organizations.id','organizations.nama', 'rooms.roomid', 'rooms.roomname', 'rooms.details', 'rooms.roompax', 'rooms.price', 'rooms.status')
                ->get();

                $nama = $data->isEmpty() ? '' : $data[0]->nama;

                return view('homestay.bookhomestay', compact('data', 'nama','homestayid'));
    }

    public function disabledateroom($roomid)
    {
        $rooms = Room::where('roomid', $roomid) // Add your additional condition here
                   ->get();

    $disabledDates = [];

    foreach ($rooms as $room) {
        $begin = new DateTime($room->checkin);
        $end = new DateTime($room->checkout);
        $interval = new DateInterval('P1D');
        $daterange = new DatePeriod($begin, $interval, $end);

        foreach ($daterange as $date) {
            $disabledDates[] = $date->format('Y-m-d');
        }
    }

    return response()->json(['disabledDates' => $disabledDates]);
    }

    public function store(Request $request)
    {
        //
    }

    public function show($id)
    {
        //
    }

    public function edit($id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        //
    }

    public function destroy($id)
    {
        //
    }
}
