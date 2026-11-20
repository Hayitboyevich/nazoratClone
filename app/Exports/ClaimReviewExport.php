<?php

namespace App\Exports;

use App\Models\ClaimOrganizationReview;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ClaimReviewExport implements FromCollection, WithHeadings
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
    * @return Collection
    */
    public function collection(): Collection
    {
        return collect($this->data);
    }

    public function headings(): array
    {
        return [
            'nomi',
            'ariza_raqami',
            'ariza_raqami_kelgan_sana',
            'mchsga_jonatilgan_vaqti',
            'mchs',
            'mchs_javobi',
            'mchs_javob_holati',
            'sesga_jonatilgan_vaqti',
            'ses',
            'ses_javobi',
            'ses_javob_holati',
            'nogironga_jonatilgan_vaqti',
            'nogiron',
            'nogiron_javobi',
            'nogiron_javob_holati',
            'nogiron2ga_jonatilgan_vaqti',
            'nogiron2',
            'nogiron2_javobi',
            'nogiron2_javob_holati',
            'kvartiraga_jonatilgan_vaqti',
            'kvartira',
            'kvartira_javobi',
            'kvartira_javob_holati',
            'ekologiyaga_jonatilgan_vaqti',
            'ekologiya',
            'ekologiya_javobi',
            'ekologiya_javob_holati',
        ];
    }
}
