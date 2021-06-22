<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Donation;
use App\User;
use Illuminate\Http\Request;
use App\Http\Requests\DonationRequest;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;

class DonationController extends Controller
{
    private $user;
    private $donation;
    private $transaction;
    private $organization;

    public function __construct(User $user, Donation $donation, Transaction $transaction, Organization $organization)
    {
        $this->user = $user;
        $this->donation = $donation;
        $this->transaction = $transaction;
        $this->organization = $organization;
    }

    public function index()
    {
        $organization = $this->getOrganizationByUserId();
        return view('donate.index', compact('organization'));
    }

    public function indexDerma()
    {
        $organization = $this->listAllOrganization();
        return view('paydonate.index', compact('organization'));
    }

    public function getDonationByOrganizationDatatable(Request $request)
    {
        if (request()->ajax()) {
            $oid = $request->oid;

            $hasOrganizaton = $request->hasOrganization;

            $userId = Auth::id();

            if ($oid != '' && !is_null($hasOrganizaton)) {
                if ($hasOrganizaton == "false") {
                    $data = DB::table('organizations')
                    ->join('donation_organization', 'donation_organization.organization_id', '=', 'organizations.id')
                    ->join('donations', 'donations.id', '=', 'donation_organization.donation_id')
                    ->select('organizations.id as oid', 'donations.id', 'donations.nama', 'donations.description', 'donations.date_started', 'donations.date_end', 'donations.status', 'donations.url')
                    ->where('organizations.id', $oid)
                    ->where('donations.status', 1)
                    ->orderBy('donations.nama');
                } else {
                    $data = DB::table('organizations')
                    ->join('donation_organization', 'donation_organization.organization_id', '=', 'organizations.id')
                    ->join('donations', 'donations.id', '=', 'donation_organization.donation_id')
                    ->select('organizations.id as oid', 'donations.id', 'donations.nama', 'donations.description', 'donations.date_started', 'donations.date_end', 'donations.status', 'donations.url')
                    ->where('organizations.id', $oid)
                    ->orderBy('donations.nama');
                }
            } elseif ($hasOrganizaton == "false") {
                $data = DB::table('donations')
                    ->join('donation_organization', 'donation_organization.donation_id', '=', 'donations.id')
                    ->join('organizations', 'organizations.id', '=', 'donation_organization.organization_id')
                    ->select('organizations.id as oid', 'donations.id', 'donations.nama', 'donations.description', 'donations.date_started', 'donations.date_end', 'donations.status', 'donations.url')
                    ->where('donations.status', 1)
                    ->orderBy('donations.nama');
            } elseif ($hasOrganizaton == "true") {
                $data = DB::table('organizations')
                    ->join('donation_organization', 'donation_organization.organization_id', '=', 'organizations.id')
                    ->join('donations', 'donations.id', '=', 'donation_organization.donation_id')
                    ->join('organization_user', 'organization_user.organization_id', '=', 'organizations.id')
                    ->join('users', 'users.id', '=', 'organization_user.user_id')
                    ->select('donations.id', 'donations.nama', 'donations.description', 'donations.date_started', 'donations.date_end', 'donations.status')
                    ->where('users.id', $userId)
                    ->orderBy('donations.nama');
            }

            // dd($data->oid);
            $table = Datatables::of($data);

            $table->addColumn('status', function ($row) {
                if ($row->status == '1') {
                    $btn = '<div class="d-flex justify-content-center">';
                    $btn = $btn . '<span class="badge badge-success">Aktif</span></div>';

                    return $btn;
                } else {
                    $btn = '<div class="d-flex justify-content-center">';
                    $btn = $btn . '<span class="badge badge-danger"> Tidak Aktif </span></div>';

                    return $btn;
                }
            });

            if ($hasOrganizaton == "false") {
                $table->addColumn('URL', function ($row) {
                    $token = csrf_field();
                    $btn = '<div class="d-flex justify-content-center">';
                    $btn = $btn . '<input type="text" readonly id="'. $row->id .'" class="form-control" value="'. URL::action('DonationController@urlDonation', array('link' => $row->url)) .'">
                    <div class="input-group-append">
                    <button id="btncopy"  onclick="copyToClipboard('. $row->id .')" class="btn btn-primary">Copy</button>
                    </div></div>';
                    return $btn;
                });
                $table->addColumn('action', function ($row) {
                    $token = csrf_field();
                    $btn = '<div class="d-flex justify-content-center">';
                    $btn = $btn . '<a href="' . route('paydonate', ['id' => $row->id]) . ' " class="btn btn-success m-1">Bayar</a></div>';
                    return $btn;
                });
            } else {
                $table->addColumn('action', function ($row) {
                    $token = csrf_token();
                    $btn = '<div class="d-flex justify-content-center">';
                    $btn = '<a href="' . route('donate.details', $row->id) . '" class="btn btn-primary m-1">Details</a>';
                    $btn = $btn . '<a href="' . route('donation.edit', $row->id) . '" class="btn btn-primary m-1">Edit</a>';
                    $btn = $btn . '<button id="' . $row->id . '" data-token="' . $token . '" class="btn btn-danger m-1">Buang</button></div>';
                    return $btn;
                });
            }
            $table->editColumn('date_started', function ($row) {
                //convert to 12 hour format
                return date('d/m/Y', strtotime($row->date_started));
            });
            $table->editColumn('date_end', function ($row) {
                //convert to 12 hour format
                return date('d/m/Y', strtotime($row->date_end));
            });
            $table->rawColumns(['status', 'URL', 'action']);
            return $table->make(true);
        }
        // return Donation::geturl();
    }

    public function listAllDonor($id)
    {
        $donation = $this->donation->getDonationById($id);

        return view('donate.donor', compact('donation'));
    }

    public function getDonorDatatable(Request $request)
    {
        $donor = $this->transaction->getDonorByDonationId($request->id);

        if (request()->ajax()) {
            return datatables()->of($donor)
                ->editColumn('amount', function ($data) {
                    return number_format($data->amount, 2);
                })
                ->editColumn('datetime_created', function ($data) {
                    $formatedDate = Carbon::createFromFormat('Y-m-d H:i:s', $data->datetime_created)->format('d/m/Y');
                    return $formatedDate;
                })
                ->make(true);
        }
    }

    public function historyDonor()
    {
        return view('donate.history');
    }

    public function getHistoryDonorDT()
    {
        $userId = Auth::id();

        $listhistory = DB::table('donations')
            ->join('donation_transaction', 'donation_transaction.donation_id', '=', 'donations.id')
            ->join('transactions', 'transactions.id', '=', 'donation_transaction.transaction_id')
            ->select('donations.nama as dname', 'transactions.amount', 'transactions.status', 'transactions.username', 'transactions.telno', 'transactions.email', 'transactions.datetime_created')
            ->where('transactions.user_id', $userId)
            ->orderBy('donations.nama')
            ->get();

        if (request()->ajax()) {
            return datatables()->of($listhistory)
                ->editColumn('datetime_created', function ($data) {
                    $formatedDate = Carbon::createFromFormat('Y-m-d H:i:s', $data->datetime_created)->format('d/m/Y');
                    return $formatedDate;
                })
                ->editColumn('amount', function ($data) {
                    return number_format($data->amount, 2);
                })
                ->addColumn('status', function ($data) {
                    if ($data->status == 'Success') {
                        $btn = '<div class="d-flex justify-content-center">';
                        $btn = $btn . '<span class="badge badge-success">Success</span></div>';
                        return $btn;
                    } elseif ($data->status == 'Pending') {
                        $btn = '<div class="d-flex justify-content-center">';
                        $btn = $btn . '<button  class="btn btn-warning m-1"> Pending </button></div>';
                        return $btn;
                    } else {
                        $btn = '<div class="d-flex justify-content-center">';
                        $btn = $btn . '<button  class="btn btn-danger m-1"> Failed </button></div>';
                        return $btn;
                    }
                })
                ->rawColumns(['status'])
                ->make(true);
        }
    }

    public function listAllOrganization()
    {
        $organization = Organization::get();
        return $organization;
    }

    public function getOrganizationByUserId()
    {
        $userId = Auth::id();

        $listorg = Organization::whereHas('user', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->get();

        return $listorg;
    }

    public function urlDonation($link)
    {
        $user = "";

        $donation = Donation::where('url', $link)->first();

        if (Auth::id()) {
            $user = $this->user->getUserById();
        }

        return view('paydonate.pay', compact('donation', 'user'));
    }

    public function create()
    {
        $organization = $this->getOrganizationByUserId();

        return view('donate.add', compact('organization'));
    }

    public function store(DonationRequest $request)
    {
        $link = explode(" ", $request->nama);
        $str = implode("-", $link);

        $start_date = Carbon::createFromFormat(config('app.date_format'), $request->date_started)->format('Y-m-d');
        $end_date = Carbon::createFromFormat(config('app.date_format'), $request->date_end)->format('Y-m-d');

        $file_name = '';
        
        if (!is_null($request->donation_poster)) {
            $storagePath  = $request->donation_poster->storeAs('public/donation-poster', 'donation-poster-'.time().'.jpg');
            $file_name = basename($storagePath);
        }

        $donation = Donation::create($request->validated() + [
            'date_created'      => now(),
            'date_started'      => $start_date,
            'date_end'          => $end_date,
            'status'            => '1',
            'url'               => $str,
            'donation_poster'   => $file_name
        ]);

        $donation->organization()->attach($request->organization);

        return redirect('/donation')->with('success', 'Derma Berjaya Ditambah');
    }

    public function show($id)
    {
        //
    }

    public function edit($id)
    {
        $organizations = $this->getOrganizationByUserId();
        $organization = $this->organization->getOrganizationByDonationId($id);
        $donation = DB::table('donations')->where('id', $id)->first();

        return view('donate.update', compact('donation', 'organization', 'organizations'));
    }

    public function update(DonationRequest $request, $id)
    {
        $link = explode(" ", $request->nama);
        $str = implode("-", $link);

        $start_date = Carbon::createFromFormat(config('app.date_format'), $request->date_started)->format('Y-m-d');
        $end_date = Carbon::createFromFormat(config('app.date_format'), $request->date_end)->format('Y-m-d');

        $file_name = '';
        
        if (!is_null($request->donation_poster)) {
            
            // Delete existing image before update with new image;
            $donation = $this->donation->getDonationById($id);
            $destination = public_path('donation-poster').  DIRECTORY_SEPARATOR  . $donation->donation_poster;
            unlink($destination);

            $storagePath  = $request->donation_poster->storeAs('public/donation-poster', 'donation-poster-'.time().'.jpg');
            $file_name = basename($storagePath);
        }

        DB::table('donations')
            ->where('id', $id)
            ->update(
                $request->validated() +
            [
                'date_created'      => now(),
                'date_started'      => $start_date,
                'date_end'          => $end_date,
                'status'            => '1',
                'url'               => $str,
                'donation_poster'   => $file_name
            ]
            );

        return redirect('/donation')->with('success', 'Derma Telah Berjaya Dikemaskini');
    }

    public function destroy($id)
    {
        // dd($id);
        $result = DB::table('donations')
                    ->where('id', '=', $id)
                    ->delete();
        

        if ($result) {
            Session::flash('success', 'Derma Berjaya Dipadam');
            return View::make('layouts/flash-messages');
        } else {
            Session::flash('error', 'Derma Gagal Dipadam');
            return View::make('layouts/flash-messages');
        }
    }

    public function test()
    {
        echo "test";
    }
}
