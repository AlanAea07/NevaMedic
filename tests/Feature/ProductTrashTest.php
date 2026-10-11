<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Sector;
use App\Models\User;
use App\Services\Catalog\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ProductTrashTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $slug = 'prueba'): Product
    {
        return Product::create(['name' => 'Producto '.$slug, 'slug' => $slug, 'short_description' => 'Prueba', 'reference_price' => 500, 'is_available' => true, 'has_variants' => true]);
    }

    public function test_trash_preserves_relations_and_restores_the_previous_state(): void
    {
        Storage::fake('public');
        $actor = User::factory()->create(['role' => 'publisher']);
        $product = $this->product();
        $variant = $product->variants()->create(['color' => 'Azul', 'color_hex' => '#0000ff', 'size' => '']);
        $product->images()->create(['path' => 'products/test.jpg', 'alt' => 'Azul', 'product_variant_id' => $variant->id]);
        Storage::disk('public')->put('products/test.jpg', 'image');
        $sector = Sector::create(['name' => 'Clínico', 'slug' => 'clinico']);
        $product->sectors()->attach($sector);

        $this->actingAs($actor)->delete('/admin/productos/'.$product->id)->assertRedirect();
        $this->assertSoftDeleted($product);
        $this->get('/productos/'.$product->slug)->assertNotFound();
        $this->get('/admin/productos')->assertDontSee($product->name);
        $this->get('/admin/productos/papelera')->assertOk()->assertSee($product->name)->assertSee('Restaurar producto');
        Storage::disk('public')->assertExists('products/test.jpg');
        $this->post('/admin/productos/papelera/'.$product->id.'/restaurar')->assertSessionHasNoErrors()->assertRedirect();
        $restored = $product->fresh();
        $this->assertFalse($restored->trashed());
        $this->assertTrue($restored->is_available);
        $this->assertCount(1, $restored->images);
        $this->assertCount(1, $restored->variants);
        $this->assertCount(1, $restored->sectors);
        $this->get('/productos/'.$product->slug)->assertOk();
        $this->assertDatabaseHas('activity_logs', ['entity_id' => $product->id, 'action' => 'restored']);
    }

    public function test_restore_deadline_is_exact_and_duplicate_restore_is_rejected(): void
    {
        $this->freezeTime();
        $actor = User::factory()->create(['role' => 'admin']);
        $expired = $this->product('expired');
        $expired->delete();
        $expired->forceFill(['deleted_at' => now()->subDays(14)])->save();
        $recent = $this->product('recent');
        $recent->delete();
        $recent->forceFill(['deleted_at' => now()->subDays(14)->addSecond()])->save();
        $this->actingAs($actor)->post('/admin/productos/papelera/'.$expired->id.'/restaurar')->assertSessionHasErrors('product');
        $this->assertSoftDeleted($expired);
        $this->post('/admin/productos/papelera/'.$recent->id.'/restaurar')->assertRedirect();
        $this->assertNotSoftDeleted($recent);
        $this->post('/admin/productos/papelera/'.$recent->id.'/restaurar')->assertNotFound();
    }

    public function test_purge_removes_only_expired_products_and_keeps_audit_and_shared_files(): void
    {
        $this->freezeTime();
        Storage::fake('public');
        $actor = User::factory()->create(['role' => 'admin', 'name' => 'Responsable original']);
        $expired = $this->product('expired');
        $recent = $this->product('recent');
        $active = $this->product('active');
        $base = 'products/12345678-1234-1234-1234-123456789abc';
        foreach ([$base.'.jpg', $base.'.webp', 'products/shared.jpg'] as $path) {
            Storage::disk('public')->put($path, 'image');
        }
        $expired->images()->create(['path' => $base, 'alt' => 'Foto']);
        $expired->images()->create(['path' => 'products/shared.jpg', 'alt' => 'Compartida']);
        $active->images()->create(['path' => 'products/shared.jpg', 'alt' => 'Compartida']);
        $expired->variants()->create(['color' => 'Azul', 'size' => '']);
        app(ProductService::class)->delete($expired, $actor);
        $expired = Product::onlyTrashed()->findOrFail($expired->id);
        $expired->forceFill(['deleted_at' => now()->subDays(14)])->save();
        $recent->delete();
        $recent->forceFill(['deleted_at' => now()->subDays(14)->addSecond()])->save();
        $actor->delete();

        $this->artisan('products:purge-trash')->assertSuccessful();
        $this->assertDatabaseMissing('products', ['id' => $expired->id]);
        $this->assertDatabaseMissing('product_variants', ['product_id' => $expired->id]);
        $this->assertDatabaseMissing('product_images', ['product_id' => $expired->id]);
        $this->assertSoftDeleted($recent);
        $this->assertNotSoftDeleted($active);
        Storage::disk('public')->assertMissing([$base.'.jpg', $base.'.webp']);
        Storage::disk('public')->assertExists('products/shared.jpg');
        $entry = ActivityLog::where('action', 'deleted')->firstOrFail();
        $this->assertSame('Responsable original', $entry->actor['name']);
        $this->assertSame('admin', $entry->actor['role']);
        $this->assertSame($base, $entry->after['images'][0]['path']);
        $purge = ActivityLog::where('action', 'purged')->firstOrFail();
        $this->assertSame('system', $purge->actor['role']);
        $this->artisan('products:purge-trash')->assertSuccessful();
        $this->assertSame(1, ActivityLog::where('action', 'purged')->count());
        $this->assertDatabaseCount('pending_image_deletions', 0);
    }

    public function test_file_cleanup_failure_is_durable_and_retried(): void
    {
        $disk = \Mockery::mock();
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);
        $disk->shouldReceive('delete')->once()->with(['products/retry.jpg'])->andReturn(false);
        $disk->shouldReceive('delete')->once()->with(['products/retry.jpg'])->andReturn(true);
        DB::table('pending_image_deletions')->insert(['path' => 'products/retry.jpg', 'created_at' => now()]);
        $this->artisan('products:purge-trash')->assertFailed();
        $this->assertDatabaseCount('pending_image_deletions', 1);
        $this->artisan('products:purge-trash')->assertSuccessful();
        $this->assertDatabaseCount('pending_image_deletions', 0);
    }

    public function test_purge_rolls_back_if_audit_cannot_be_saved(): void
    {
        $product = $this->product();
        $product->delete();
        $product->forceFill(['deleted_at' => now()->subDays(15)])->save();
        $product->images()->create(['path' => 'products/rollback.jpg', 'alt' => 'Foto']);
        ActivityLog::creating(fn () => throw new RuntimeException('Audit unavailable'));
        try {
            $this->artisan('products:purge-trash')->assertFailed();
            $this->assertSoftDeleted($product);
            $this->assertDatabaseHas('product_images', ['product_id' => $product->id]);
            $this->assertDatabaseCount('pending_image_deletions', 0);
        } finally {
            ActivityLog::flushEventListeners();
        }
    }

    public function test_audit_details_show_snapshots_and_permissions_are_enforced(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Nombre original']);
        $product = $this->product();
        app(ProductService::class)->delete($product, $admin);
        $entry = ActivityLog::where('action', 'deleted')->firstOrFail();
        $admin->update(['name' => 'Nombre nuevo']);
        $this->actingAs($admin)->get('/admin/actividad/'.$entry->id)->assertOk()->assertSee('Nombre original')->assertSee('En papelera')->assertSee($product->name)->assertSee('Datos anteriores');
        $publisher = User::factory()->create(['role' => 'publisher']);
        $this->actingAs($publisher)->get('/admin/actividad/'.$entry->id)->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/productos/papelera')->assertForbidden();
        $this->post('/admin/productos/papelera/'.$product->id.'/restaurar')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'publisher', 'is_active' => false]))->post('/admin/productos/papelera/'.$product->id.'/restaurar')->assertForbidden();
    }

    public function test_product_creation_and_edit_capture_before_after_and_upload_details(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $sector = Sector::create(['name' => 'Clínico', 'slug' => 'clinico']);
        $data = ['name' => 'Nombre inicial', 'slug' => 'prueba', 'short_description' => 'Prueba', 'reference_price' => 500, 'is_available' => 1, 'is_featured' => 0, 'sectors' => [$sector->id], 'image_source' => 'file', 'image_file' => UploadedFile::fake()->image('uniforme.jpg')];
        $this->post('/admin/productos', $data)->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $created = ActivityLog::where('action', 'created')->where('entity_type', 'product')->firstOrFail();
        $this->assertNull($created->before);
        $this->assertSame('uniforme.jpg', $created->metadata['uploads'][0]['name']);
        $this->assertCount(1, $created->after['images']);
        unset($data['image_file']);
        $data['image_source'] = 'keep';
        $data['name'] = 'Nombre actualizado';
        $this->put('/admin/productos/'.$product->id, $data)->assertSessionHasNoErrors();
        $updated = ActivityLog::where('action', 'updated')->where('entity_type', 'product')->firstOrFail();
        $this->assertSame('Nombre inicial', $updated->before['name']);
        $this->assertSame('Nombre actualizado', $updated->after['name']);
        $this->assertSame(2, ActivityLog::where('entity_type', 'product')->count());
    }

    public function test_authenticated_actions_are_logged_without_passwords_or_tokens(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertSessionHasNoErrors();
        $this->get('/admin/productos')->assertOk();
        $this->post('/logout')->assertRedirect();
        $this->assertDatabaseHas('activity_logs', ['action' => 'login']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'visited']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'logout']);
        $this->assertStringNotContainsString('password', ActivityLog::all()->toJson());
        $this->assertStringNotContainsString('_token', ActivityLog::all()->toJson());
    }
}
