<?php

namespace Tests\Feature\Listings;

use App\Models\Agent;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PropertyMediaTest extends TestCase
{
    /**
     * A real JPEG with an EXIF (APP1) segment carrying a fake GPS marker.
     */
    private function jpegWithExif(int $w = 800, int $h = 600): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 30, 90, 140));
        ob_start();
        imagejpeg($img);
        $jpeg = (string) ob_get_clean();

        $payload = "Exif\0\0".'GPSLatitude=40.7440;GPSLongitude=-74.0324';
        $app1 = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        return substr($jpeg, 0, 2).$app1.substr($jpeg, 2);
    }

    private function setUpListing(): array
    {
        Storage::fake('public');
        $agent = Agent::factory()->create();
        $property = Property::factory()->forAgent($agent)->create();
        Sanctum::actingAs(User::find($agent->user_id)->fresh());

        return [$agent, $property];
    }

    public function test_upload_strips_metadata_and_stores_privately_named_file(): void
    {
        [, $property] = $this->setUpListing();
        $source = $this->jpegWithExif();
        $this->assertStringContainsString('GPSLatitude', $source);

        $response = $this->post("/api/v1/manage/properties/{$property->id}/media", [
            'photo' => UploadedFile::fake()->createWithContent('my-house.jpg', $source),
            'caption' => 'Front',
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.width', 800)
            ->assertJsonPath('data.mime_type', 'image/jpeg');

        $media = PropertyMedia::findOrFail($response->json('data.id'));
        $this->assertMatchesRegularExpression('#^properties/\d+/[0-9a-f-]{36}\.jpg$#', $media->path);
        $stored = Storage::disk('public')->get($media->path);
        $this->assertStringNotContainsString('GPSLatitude', $stored, 'EXIF/GPS metadata removed');
        $this->assertStringNotContainsString('Exif', $stored);
    }

    public function test_rejects_non_images_small_images_and_enforces_limit(): void
    {
        [, $property] = $this->setUpListing();

        $this->post("/api/v1/manage/properties/{$property->id}/media", [
            'photo' => UploadedFile::fake()->createWithContent('evil.jpg', '<?php echo "x";'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->post("/api/v1/manage/properties/{$property->id}/media", [
            'photo' => UploadedFile::fake()->createWithContent('tiny.jpg', $this->jpegWithExif(200, 150)),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['fields' => ['photo']]]]);

        config(['app_inmobiliaria.media.max_photos' => 1]);
        $upload = fn () => $this->post("/api/v1/manage/properties/{$property->id}/media", [
            'photo' => UploadedFile::fake()->createWithContent('ok.jpg', $this->jpegWithExif()),
        ], ['Accept' => 'application/json']);
        $upload()->assertCreated();
        $upload()->assertStatus(422)->assertJsonPath('error.code', 'media_limit_reached');
    }

    public function test_reorder_and_delete_only_own_listing_media(): void
    {
        [, $property] = $this->setUpListing();
        $ids = [];
        foreach ([1, 2] as $i) {
            $ids[] = $this->post("/api/v1/manage/properties/{$property->id}/media", [
                'photo' => UploadedFile::fake()->createWithContent("p{$i}.jpg", $this->jpegWithExif()),
            ], ['Accept' => 'application/json'])->json('data.id');
        }

        $this->putJson("/api/v1/manage/properties/{$property->id}/media/order", ['order' => array_reverse($ids)])
            ->assertOk()->assertJsonPath('data.0.id', $ids[1]);

        $foreign = Property::factory()->create();
        $this->deleteJson("/api/v1/manage/properties/{$foreign->id}/media/{$ids[0]}")->assertForbidden();

        $path = PropertyMedia::find($ids[0])->path;
        $this->deleteJson("/api/v1/manage/properties/{$property->id}/media/{$ids[0]}")->assertNoContent();
        Storage::disk('public')->assertMissing($path);
    }
}
