<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use App\Exports\PolimasSutdentExport;
use App\Exports\PolimasAllSutdentExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use function PHPUnit\Framework\isEmpty;
use function PHPUnit\Framework\isNull;

class PolimasController extends Controller
{
    //
    private $oid = 107;

    public function indexLogin($name)
    {
        $title="";
        $placeholder="Masukkan Email/Nombor Telefon/Nombor IC";
        switch($name)
        {
            case "lmm":
                $oid=137;
                $placeholder="Masukkan Email/Nombor IC";
                $title="Lembaga Maktab Mahmud";
                break;
            case "polimas":
                $oid =107;
                $title="Polimas";
                break;
             default:
                return redirect('/login');

        }

        $org=DB::table('organizations')->where('id',$oid)->first();
        //dd($org);
        return view('polimas.index',compact('org','placeholder','title'));
    }

    public function indexBatch()
    {
        $organization = DB::table('organizations')
            ->where('id', $this->oid)
            ->first();

        return view('polimas.batch', compact('organization'));
    }
    
    public function getBatchDataTable(Request $request)
    {
        if (request()->ajax()) {

            $data = DB::table('classes')
                    ->join('class_organization', 'class_organization.class_id', '=', 'classes.id')
                    ->leftJoin('organization_user', 'class_organization.organ_user_id', 'organization_user.id')
                    ->leftJoin('users', 'organization_user.user_id', 'users.id')
                    ->select('classes.id as cid', 'classes.nama as cnama', 'classes.levelid', 'users.name as guru')
                    ->where([
                        ['class_organization.organization_id', $this->oid],
                        ['classes.status', "1"]
                    ])
                    ->orderBy('classes.nama')
                    ->orderBy('classes.levelid');

            $table = Datatables::of($data);

            $table->addColumn('totalstudent', function ($row) {

                $list_student = DB::table('class_organization')
                    ->join('class_student', 'class_student.organclass_id', '=', 'class_organization.id')
                    ->join('classes', 'classes.id', '=', 'class_organization.class_id')
                    ->join('students', 'students.id', '=', 'class_student.student_id')
                    ->select('classes.nama', DB::raw('COUNT(students.id) as totalstudent'))
                    ->where('classes.id', $row->cid)
                    ->where('class_student.status', 1)
                    ->groupBy('classes.nama')
                    ->first();

                if ($list_student) {
                    $btn = '<div class="d-flex justify-content-center">' . $list_student->totalstudent . '</div>';
                    return $btn;
                } else {
                    $btn = '<div class="d-flex justify-content-center"> 0 </div>';
                    return $btn;
                }
            });

            $table->addColumn('action', function ($row) {
                $token = csrf_token();
                $btn = '<div class="d-flex justify-content-center">';
                $btn = $btn . '<a href="' . route('class.edit', $row->cid) . '" class="btn btn-primary m-1">Edit</a>';
                $btn = $btn . '<button id="' . $row->cid . '" data-token="' . $token . '" class="btn btn-danger m-1">Buang</button></div>';
                return $btn;
            });

            $table->rawColumns(['totalstudent', 'action']);
            return $table->make(true);
        }
    }


    public function konvoChart(Request $request){
  
        $class=DB::table('classes')->where('id',$request->class)->first();
        $organization = DB::table('organizations')
        ->where('id', $request->orgId)
        ->first();
        $allFees = DB::table('fees_new as fn')
            ->join('student_fees_new as sfn', 'sfn.fees_id','fn.id')
            ->join('class_student as cs','cs.id','sfn.class_student_id')
            ->join('class_organization as co','co.id','cs.organclass_id')
            ->where('fn.organization_id', $organization->id)
            ->where('co.class_id', $class->id)
            ->where('fn.name','LIKE','%HADIR%')
            ->select('fn.*')
            ->distinct();
        
        $allFeesId = $allFees->get()->pluck('id');
        
        $hadirFees = DB::table('fees_new')
            ->whereIn('id', $allFeesId)
            ->where('name', 'NOT LIKE', '%TIDAK%')
            ->get();
        $hadirFeesId=  $hadirFees->pluck('id');
        
        $tidakHadirFees = DB::table('fees_new')
            ->whereIn('id', $allFeesId)
            ->where('name', 'LIKE', '%TIDAK%')
            ->get();
        
        $tidakhadirFeesId = $tidakHadirFees->pluck('id');

        $batch1_totalStudent = DB::table('class_organization')
        ->join('class_student', 'class_student.organclass_id', '=', 'class_organization.id')
        ->join('classes', 'classes.id', '=', 'class_organization.class_id')
        ->join('students', 'students.id', '=', 'class_student.student_id')
        ->join('student_fees_new','student_fees_new.class_student_id','class_student.id')
        ->where([
            'classes.id' => $class->id,
            'class_organization.organization_id' => $this->oid
        ])
        ->select('class_student.id')
        ->distinct()
        ->get()
        ->pluck('id');
    
        $batch1_hadir = DB::table('student_fees_new')
            ->where([
                'status' => 'Paid',
            ])
            ->whereIn('fees_id', $hadirFeesId)
            ->whereIn('class_student_id',$batch1_totalStudent)
            ->count();
    
        $batch1_tidakhadir = DB::table('student_fees_new')
            ->where([
                'status' => 'Paid',
            ])
            ->whereIn('fees_id', $tidakhadirFeesId)
            ->whereIn('class_student_id',$batch1_totalStudent)
            ->count();
        
        $batch1_hutang = count($batch1_totalStudent) - $batch1_hadir - $batch1_tidakhadir;

        $batch1 = [
            'hadir'         => $batch1_hadir,
            'tidak_hadir'   => $batch1_tidakhadir,
            'hutang'        => $batch1_hutang
        ];

        return response()->json(['batch1'=>$batch1,'class'=>$class,'allfee'=>$allFeesId]);
    }
    public function indexStudent()
    {
        $organization = DB::table('organizations')
            ->where('id', $this->oid)
            ->first();

        $class=DB::table('class_organization as co')
                ->join('classes as c', 'c.id', '=', 'co.class_id')
                ->where('co.organization_id',$organization->id)
                ->get();

        

       
        
        $batch2_totalStudent = DB::table('class_organization')
            ->join('class_student', 'class_student.organclass_id', '=', 'class_organization.id')
            ->join('classes', 'classes.id', '=', 'class_organization.class_id')
            ->join('students', 'students.id', '=', 'class_student.student_id')
            ->where([
                'classes.levelid' => 2,
                'class_organization.organization_id' => $this->oid
            ])
            ->count();
        
        $batch2_hadir = DB::table('student_fees_new')
            ->where([
                'status' => 'Paid',
                'fees_id' => 247
            ])
            ->count();
        
        $batch2_tidakhadir = DB::table('student_fees_new')
            ->where([
                'status' => 'Paid',
                'fees_id' => 248
            ])
            ->count();
        
        $batch2_hutang = $batch2_totalStudent - $batch2_hadir - $batch2_tidakhadir;

        $batch2 = [
            'hadir'         => $batch2_hadir,
            'tidak_hadir'   => $batch2_tidakhadir,
            'hutang'        => $batch2_hutang
        ];

        return view('polimas.student', compact('organization',  'batch2',));
    }

    public function getStudentDatatable(Request $request)
    {
        // dd($request->oid);

        if (request()->ajax()) {
            // $oid = $request->oid;
            $classid = $request->classid;

            $hasOrganizaton = $request->hasOrganization;

            $userId = Auth::id();

            if ($classid != '' && !is_null($hasOrganizaton)) {
                $data = DB::table('students')
                    ->join('class_student', 'class_student.student_id', '=', 'students.id')
                    ->join('class_organization', 'class_organization.id', '=', 'class_student.organclass_id')
                    ->join('classes', 'classes.id', '=', 'class_organization.class_id')
                    ->select('students.id as id',  'students.nama as studentname', 'students.icno', 'classes.nama as classname', 'class_student.student_id as csid')
                    ->where([
                        ['classes.id', $classid],
                        ['class_student.status', 1],
                    ])
                    ->orderBy('students.nama');

                $table = Datatables::of($data);

                $table->addColumn('status', function ($row) {
                    $isPaid = DB::table('student_fees_new as sfn')
                        ->leftJoin('fees_new as fn', 'fn.id', 'sfn.fees_id')
                        ->where('sfn.status', 'Paid')
                        ->where('sfn.class_student_id', $row->csid)
                        ->select('fn.name')
                        ->first();
                    
                    if ($isPaid)
                    {
                        if (strpos($isPaid->name, 'Tidak Hadir'))
                        {
                            $btn = '<div class="d-flex justify-content-center">';
                            $btn = $btn . '<span class="badge badge-success">Tidak Hadir</span></div>';
                            return $btn;
                        }
                        else
                        {
                            $btn = '<div class="d-flex justify-content-center">';
                            $btn = $btn . '<span class="badge badge-success">Hadir</span></div>';
                            return $btn;
                        }
                    }
                    else
                    {
                        $btn = '<div class="d-flex justify-content-center">';
                        $btn = $btn . '<span class="badge badge-danger">  Belum Bayar </span></div>';
                        return $btn;
                    }
                });

                $table->addColumn('action', function ($row) {
                    $token = csrf_token();
                    $btn = '<div class="d-flex justify-content-center">';
                    $btn = $btn . '<a class="btn btn-primary m-1 student-id" id="' . $row->id . '">Butiran</a></div>';
                    return $btn;
                });

                $table->rawColumns(['status', 'action']);
                return $table->make(true);
            }
        }
    }

    public function student_fees(Request $request)
    {
        $student_id = $request->student_id;
        $getfees_bystudent = DB::table('students')
            ->join('class_student', 'class_student.student_id', '=', 'students.id')
            ->join('student_fees_new', 'student_fees_new.class_student_id', '=', 'class_student.id')
            ->join('fees_new', 'fees_new.id', '=', 'student_fees_new.fees_id')
            ->select('fees_new.*','students.id as studentid', 'students.nama as studentnama', 'student_fees_new.status')
            ->where('students.id', $student_id)
            ->where('student_fees_new.status', 'Paid')
            ->orderBy('fees_new.name')
            ->get();
        
        if($getfees_bystudent->isEmpty())
        {
            $getfees_bystudent = DB::table('students')
            ->join('class_student', 'class_student.student_id', '=', 'students.id')
            ->join('student_fees_new', 'student_fees_new.class_student_id', '=', 'class_student.id')
            ->join('fees_new', 'fees_new.id', '=', 'student_fees_new.fees_id')
            ->select('fees_new.*','students.id as studentid', 'students.nama as studentnama', 'student_fees_new.status')
            ->where('students.id', $student_id)
            ->orderBy('fees_new.name')
            ->get();
        }

        return response()->json($getfees_bystudent, 200);
    }

    public function StudentExport(Request $request)
    {
        // dd($request);
        $class = DB::table('classes')
            ->where('id', $request->classExport)
            ->first();

        return Excel::download(new PolimasSutdentExport($this->oid, $request->classExport), $class->nama . '.xlsx');
    }

    public function AllStudentExport(Request $request)
    {
        return Excel::download(new PolimasAllSutdentExport(), 'Polimas.xlsx');
    }
}
