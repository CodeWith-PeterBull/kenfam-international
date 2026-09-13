<?php

namespace App\Services;

use App\Contracts\RecordsSystemActivity;
use App\Contracts\ResolvesInstitutionProfile;
use App\Enums\SystemActivitySeverity;
use App\Models\InstitutionDetail;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final readonly class InstitutionDetailsService
{
    public function __construct(
        private DatabaseManager $database,
        private RecordsSystemActivity $activities,
        private ResolvesInstitutionProfile $profiles,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function save(
        array $attributes,
        ?UploadedFile $mainLogo,
        ?UploadedFile $logoIcon,
        User $actor,
    ): InstitutionDetail {
        $detail = $this->database->transaction(function () use ($attributes, $mainLogo, $logoIcon, $actor): InstitutionDetail {
            $detail = InstitutionDetail::query()->find(InstitutionDetail::PRIMARY_ID);
            $created = $detail === null;
            $detail ??= new InstitutionDetail(['id' => InstitutionDetail::PRIMARY_ID]);
            $detail->forceFill(['id' => InstitutionDetail::PRIMARY_ID]);
            $detail->fill($attributes);
            $detail->save();

            $changedFields = array_values(array_diff(array_keys($detail->getChanges()), ['created_at', 'updated_at']));
            $mainLogoUpdated = $this->replaceMedia($detail, 'main_logo', $mainLogo);
            $logoIconUpdated = $this->replaceMedia($detail, 'logo_icon', $logoIcon);

            $this->activities->record(
                activityType: $created ? 'institution_detail.created' : 'institution_detail.updated',
                description: $created
                    ? "Institution details created for {$detail->name}"
                    : "Institution details updated for {$detail->name}",
                actor: $actor,
                subject: $detail,
                properties: [
                    'changed_fields' => $changedFields,
                    'main_logo_updated' => $mainLogoUpdated,
                    'logo_icon_updated' => $logoIconUpdated,
                ],
                severity: SystemActivitySeverity::Notice,
                source: 'institution-details',
            );

            return $detail->refresh();
        });

        $this->profiles->forget();

        return $detail;
    }

    public function removeLogo(string $collection, User $actor): void
    {
        if (! in_array($collection, ['main_logo', 'logo_icon'], true)) {
            throw new \InvalidArgumentException('Unsupported institution logo collection.');
        }

        $this->database->transaction(function () use ($collection, $actor): void {
            $detail = InstitutionDetail::query()->findOrFail(InstitutionDetail::PRIMARY_ID);
            if (! $detail->hasMedia($collection)) {
                return;
            }

            $detail->clearMediaCollection($collection);
            $this->activities->record(
                activityType: 'institution_detail.logo_removed',
                description: "Institution {$collection} asset removed",
                actor: $actor,
                subject: $detail,
                properties: ['collection' => $collection],
                severity: SystemActivitySeverity::Notice,
                source: 'institution-details',
            );
        });

        $this->profiles->forget();
    }

    private function replaceMedia(InstitutionDetail $detail, string $collection, ?UploadedFile $upload): bool
    {
        if (! $upload) {
            return false;
        }

        $extension = strtolower($upload->guessExtension() ?: 'png');
        $detail->addMedia($upload)
            ->usingFileName(Str::uuid().'.'.$extension)
            ->toMediaCollection($collection);

        return true;
    }
}
