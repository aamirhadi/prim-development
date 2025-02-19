<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Symfony\Component\VarDumper\Cloner\Data;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ExportYuranStatus implements FromCollection, ShouldAutoSize, WithHeadings
{
    public function __construct($yuran)
    {
        $this->yuran = $yuran;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        if($this->yuran->category == "Kategori A")
        {
            
            $org=DB::table('organizations as o')
                    ->where('o.id',$this->yuran->organization_id)
                    ->first();

            $orgId=$org->id;
            if($org->parent_org!=null ){
                $orgId=$org->parent_org;
            }
            $feeStatus = DB::table('fees_new_organization_user as fou')
                    ->leftJoin('organization_user as ou','ou.id','fou.organization_user_id')
                    ->where('fou.fees_new_id', $this->yuran->id)
                    ->select('ou.user_id','fou.status')
                    ->distinct('fou.id')
                    ->get();

            $student=DB::table('organization_user as ou')
                ->leftJoin('organization_user_student as ous', 'ous.organization_user_id', 'ou.id')
                ->leftJoin('students as s', 's.id', 'ous.student_id')
                ->leftJoin('class_student as cs', 'cs.student_id', 's.id')
                ->leftJoin('class_organization as co', 'co.id', 'cs.organclass_id')
                ->leftJoin('classes as c','c.id','co.class_id')
                ->where('ou.organization_id', $orgId)
                ->select('s.nama', 'c.nama as nama_kelas', 's.gender', 'ou.user_id')
                ->orderBy('c.nama')
                ->orderBy('s.nama')
                ->get();
            
            $data = $feeStatus->map(function ($fee) use ($student) {
                $studentItem = $student->where('user_id', $fee->user_id)->first();
                return (object) array_merge((array) $studentItem, (array) $fee);
            });
            

            foreach ($data as &$item) {
                unset($item->user_id);
            }
            //dd($data);
        }
        else
        {
            $data = DB::table('students as s')
                ->leftJoin('class_student as cs', 'cs.student_id', 's.id')
                ->leftJoin('class_organization as co', 'co.id', 'cs.organclass_id')
                ->leftJoin('classes as c', 'c.id', 'co.class_id')
                ->leftJoin('student_fees_new as sfn', 'sfn.class_student_id', 'cs.id')
                ->where('sfn.fees_id', $this->yuran->id)
                ->select('s.nama', 'c.nama as nama_kelas', 's.gender', 'sfn.status')
                ->orderBy('c.nama')
                ->orderBy('s.nama')
                ->get();
        }
        
        foreach ($data as $key => $student) {
            $student->status = $student->status == "Debt" ? "Masih Berhutang" : "Telah Bayar";
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            'Nama',
            'Kelas',
            'Jantina',
            'Status',
        ];
    }
}
