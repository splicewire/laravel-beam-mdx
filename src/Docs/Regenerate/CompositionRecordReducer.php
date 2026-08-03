<?php

namespace Splicewire\Beam\Mdx\Docs\Regenerate;

/**
 * The `jq` reduction the profile guides applied to a raw GET /compositions/{id} record: identity,
 * the cell-profile histogram (grouped + key-sorted, as `jq group_by` produces), and the first
 * cell's sorted slot keys. Shared by the `profiles` and `profiles-guide` guides so the reduction
 * lives in one place instead of two bash-interpolated `jq` filters.
 */
class CompositionRecordReducer
{
    /**
     * @param  array<string, mixed>  $data  the `data` object of the composition response
     * @return array<string, mixed>
     */
    public static function reduce(array $data): array
    {
        $cells = (array) data_get($data, 'cells', []);

        $cellProfiles = [];
        foreach ($cells as $cell) {
            $profile = (string) data_get($cell, 'profile');
            $cellProfiles[$profile] = ($cellProfiles[$profile] ?? 0) + 1;
        }
        ksort($cellProfiles);

        $firstCell = $cells[0] ?? [];
        $slotKeys = array_keys((array) data_get($firstCell, 'slots', []));
        sort($slotKeys);

        return [
            'composition' => [
                'id' => data_get($data, 'id'),
                'profile' => data_get($data, 'profile'),
                'status' => data_get($data, 'status'),
            ],
            'cell_profiles' => $cellProfiles,
            'first_cell' => [
                'profile' => data_get($firstCell, 'profile'),
                'slot_keys' => $slotKeys,
            ],
        ];
    }
}
