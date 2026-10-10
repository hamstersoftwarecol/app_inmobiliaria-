<?php

namespace App\Application\Listings;

use App\Application\Audit\AuditLogger;
use App\Domain\Audit\AuditCategory;
use App\Domain\Listings\Exceptions\ListingException;
use App\Domain\Shared\DomainException;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photo uploads for internal listings. Images are decoded and re-encoded
 * with GD before storage, which strips EXIF metadata (including GPS
 * coordinates of the photographer) and rejects files that are not images.
 */
class PropertyMediaService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function upload(User $actor, Property $property, UploadedFile $file, ?string $caption): PropertyMedia
    {
        if ($property->isMls()) {
            throw ListingException::mlsReadOnly();
        }
        $max = (int) config('hitlease.media.max_photos', 50);
        if ($property->media()->count() >= $max) {
            throw ListingException::mediaLimitReached($max);
        }

        [$binary, $mime, $extension, $width, $height] = $this->reencode($file);
        $disk = (string) config('hitlease.media.disk', 'public');
        $path = "properties/{$property->id}/".Str::uuid().".{$extension}";
        Storage::disk($disk)->put($path, $binary, ['visibility' => 'public']);

        $media = PropertyMedia::query()->create([
            'property_id' => $property->id,
            'source' => 'upload',
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $mime,
            'size_bytes' => strlen($binary),
            'width' => $width,
            'height' => $height,
            'caption' => $caption,
            'sort_order' => (int) $property->media()->max('sort_order') + 1,
            'display_allowed' => true,
        ]);

        $this->audit->log(AuditCategory::Data, 'properties.media_uploaded', $actor, $property, new: ['media_id' => $media->id]);

        return $media;
    }

    /**
     * @param  list<int>  $orderedIds
     */
    public function reorder(Property $property, array $orderedIds): void
    {
        DB::transaction(function () use ($property, $orderedIds) {
            foreach (array_values($orderedIds) as $i => $id) {
                PropertyMedia::query()->where('property_id', $property->id)->whereKey($id)->update(['sort_order' => $i]);
            }
        });
    }

    public function delete(User $actor, PropertyMedia $media): void
    {
        if ($media->source !== 'upload') {
            throw ListingException::mlsReadOnly();
        }
        if ($media->disk && $media->path) {
            Storage::disk($media->disk)->delete($media->path);
        }
        $media->delete();
        $this->audit->log(AuditCategory::Data, 'properties.media_deleted', $actor, $media->property, old: ['media_id' => $media->id]);
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: int, 4: int}
     */
    private function reencode(UploadedFile $file): array
    {
        $raw = (string) file_get_contents($file->getRealPath());
        $info = @getimagesizefromstring($raw);
        $image = $info ? @imagecreatefromstring($raw) : false;
        if (! $info || $image === false) {
            throw new DomainException('invalid_image', 'The uploaded file is not a valid image.', 422);
        }

        ob_start();
        switch ($info[2]) {
            case IMAGETYPE_PNG:
                imagesavealpha($image, true);
                imagepng($image, null, 6);
                $mime = 'image/png';
                $ext = 'png';
                break;
            case IMAGETYPE_WEBP:
                imagewebp($image, null, 85);
                $mime = 'image/webp';
                $ext = 'webp';
                break;
            default:
                imageinterlace($image, true);
                imagejpeg($image, null, 85);
                $mime = 'image/jpeg';
                $ext = 'jpg';
        }
        $binary = (string) ob_get_clean();

        return [$binary, $mime, $ext, (int) $info[0], (int) $info[1]];
    }
}
