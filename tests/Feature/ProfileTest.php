<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_profile_edit_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)->get('/profile');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Profile/Edit')
            ->has('user')
        );
    }

    public function test_profile_edit_page_shows_user_data(): void
    {
        $response = $this->actingAs($this->user)->get('/profile');

        $response->assertInertia(fn ($page) => $page
            ->has('user.id')
            ->has('user.name')
            ->has('user.email')
        );
    }

    public function test_user_can_update_profile(): void
    {
        $response = $this->actingAs($this->user)->put('/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '+237 699 000 000',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '+237 699 000 000',
        ]);
    }

    public function test_profile_update_requires_name(): void
    {
        $response = $this->actingAs($this->user)->put('/profile', [
            'email' => 'test@example.com',
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_profile_update_requires_valid_email(): void
    {
        $response = $this->actingAs($this->user)->put('/profile', [
            'name' => 'Test User',
            'email' => 'invalid-email',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_profile_update_requires_unique_email(): void
    {
        $otherUser = User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->actingAs($this->user)->put('/profile', [
            'name' => 'Test User',
            'email' => 'taken@example.com',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_user_can_keep_same_email(): void
    {
        $response = $this->actingAs($this->user)->put('/profile', [
            'name' => 'Updated Name',
            'email' => $this->user->email,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_user_can_update_password(): void
    {
        $this->user->update(['password' => Hash::make('oldpassword')]);

        $response = $this->actingAs($this->user)->put('/profile/password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect();
        $this->assertTrue(Hash::check('newpassword123', $this->user->fresh()->password));
    }

    public function test_password_update_requires_current_password(): void
    {
        $response = $this->actingAs($this->user)->put('/profile/password', [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors(['current_password']);
    }

    public function test_password_update_validates_current_password(): void
    {
        $response = $this->actingAs($this->user)->put('/profile/password', [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors(['current_password']);
    }

    public function test_password_update_requires_confirmation(): void
    {
        $this->user->update(['password' => Hash::make('oldpassword')]);

        $response = $this->actingAs($this->user)->put('/profile/password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    public function test_user_can_upload_avatar(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->user)->post('/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

        $response->assertRedirect();
        $this->assertNotNull($this->user->fresh()->avatar);
        Storage::disk('public')->assertExists($this->user->fresh()->avatar);
    }

    public function test_avatar_upload_requires_image(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->user)->post('/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('document.pdf', 100),
        ]);

        $response->assertSessionHasErrors(['avatar']);
    }

    public function test_avatar_upload_has_size_limit(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->user)->post('/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg')->size(3000),
        ]);

        $response->assertSessionHasErrors(['avatar']);
    }

    public function test_old_avatar_is_deleted_on_upload(): void
    {
        Storage::fake('public');

        // Upload first avatar
        $this->actingAs($this->user)->post('/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar1.jpg'),
        ]);
        $firstAvatar = $this->user->fresh()->avatar;

        // Upload second avatar
        $this->actingAs($this->user)->post('/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar2.jpg'),
        ]);

        Storage::disk('public')->assertMissing($firstAvatar);
    }

    public function test_user_can_delete_avatar(): void
    {
        Storage::fake('public');

        // First upload an avatar
        $this->actingAs($this->user)->post('/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ]);
        $avatarPath = $this->user->fresh()->avatar;

        // Then delete it
        $response = $this->actingAs($this->user)->delete('/profile/avatar');

        $response->assertRedirect();
        $this->assertNull($this->user->fresh()->avatar);
        Storage::disk('public')->assertMissing($avatarPath);
    }

    public function test_user_can_update_theme_to_dark(): void
    {
        $response = $this->actingAs($this->user)->put('/profile/theme', [
            'theme' => 'dark',
        ]);

        $response->assertRedirect();
        $this->assertEquals('dark', $this->user->fresh()->theme);
    }

    public function test_user_can_update_theme_to_light(): void
    {
        $this->user->update(['theme' => 'dark']);

        $response = $this->actingAs($this->user)->put('/profile/theme', [
            'theme' => 'light',
        ]);

        $response->assertRedirect();
        $this->assertEquals('light', $this->user->fresh()->theme);
    }

    public function test_theme_update_requires_valid_theme(): void
    {
        $response = $this->actingAs($this->user)->put('/profile/theme', [
            'theme' => 'invalid',
        ]);

        $response->assertSessionHasErrors(['theme']);
    }

    public function test_profile_requires_authentication(): void
    {
        $response = $this->get('/profile');

        $response->assertRedirect('/login');
    }
}
