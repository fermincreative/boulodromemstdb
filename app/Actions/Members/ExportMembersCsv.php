<?php

namespace App\Actions\Members;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams members as a CSV that opens correctly in Dutch Excel (UTF-8 with BOM, semicolons).
 * Nothing is written to disk; every export is logged because it contains personal data.
 */
class ExportMembersCsv
{
    /**
     * Column header => value for one member.
     *
     * @return array<string, string|int|null>
     */
    public function row(Member $member): array
    {
        $membership = $member->currentMembership;

        return [
            'Lidnummer' => $member->display_number,
            'Voornaam' => $member->first_name,
            'Tussenvoegsel' => $member->middle_name,
            'Achternaam' => $member->last_name,
            'E-mailadres' => $member->email,
            'Mobiel' => $member->phone_mobile,
            'Telefoon thuis' => $member->phone_home,
            'Adres' => $member->street,
            'Postcode' => $member->postal_code,
            'Woonplaats' => $member->city,
            'Land' => $member->country,
            'Verjaardag' => $member->birthday?->format('d-m-Y'),
            'Vereniging' => $member->club?->name ?? 'Geen',
            'Contactpersoon van' => $member->contact_for,
            'Lid sinds' => $member->joined_at?->format('d-m-Y'),
            'Geldig t/m' => $membership?->ends_at->format('d-m-Y'),
            'Kaart verstuurd' => $membership?->card_sent_at?->format('d-m-Y'),
            'Opgezegd op' => $member->cancelled_at?->format('d-m-Y'),
            'Notities' => $member->notes,
        ];
    }

    /**
     * @param  Builder<Member>  $query
     */
    public function download(Builder $query, string $description): StreamedResponse
    {
        $query = $query->clone()->with(['club', 'currentMembership']);
        $count = $query->count();

        activity('members')
            ->event('exported')
            ->withProperties(['count' => $count, 'selection' => $description])
            ->log("Ledenlijst geëxporteerd ({$count} leden)");

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            $header = false;
            foreach ($query->lazy(100) as $member) {
                $row = $this->row($member);

                if (! $header) {
                    fputcsv($out, array_keys($row), ';', '"', '');
                    $header = true;
                }

                fputcsv($out, array_map($this->safe(...), array_values($row)), ';', '"', '');
            }

            if (! $header) {
                fputcsv($out, array_keys($this->row(new Member)), ';', '"', '');
            }

            fclose($out);
        }, 'leden-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Prevents spreadsheet formula injection ("=HYPERLINK(...)" in a name), but leaves phone numbers like +31 6 … alone.
     */
    private function safe(string|int|null $value): string
    {
        $value = (string) $value;

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            && ! preg_match('/^[+\-]?[\d\s()\-.]+$/', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
