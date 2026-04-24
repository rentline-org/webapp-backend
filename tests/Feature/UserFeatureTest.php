<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\Mock\UserMockData;
use Tests\TestCase;

class UserFeatureTest extends TestCase
{
    use RefreshDatabase;

    public $adminUser;
    public $authToken;

    public function test_can_get_all_users()
    {
        User::factory()->count(3)->create();

        $response = $this->withHeaders([
            'Authorization' => "Bearer $this->authToken",
            'Accept' => 'application/json',
        ])->getJson('/api/v1/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'phone',
                        'roles',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_can_create_a_new_user()
    {
        $userData = UserMockData::getUserData();
        // Use a unique email to avoid conflict with admin user
        $userData['email'] = 'newuser@example.com';
        $userData['password_confirmation'] = $userData['password'];
        $userData['is_active'] = true;
        $roleId = $this->adminUser->roles->first()->id;
        $userData['roles'] = [$roleId];

        $response = $this->withHeaders([
            'Authorization' => "Bearer $this->authToken",
            'Accept' => 'application/json',
        ])->postJson('/api/v1/user', $userData);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', $userData['name'])
            ->assertJsonPath('data.email', $userData['email']);
    }

    public function test_can_show_user_details()
    {
        $user = User::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => "Bearer $this->authToken",
            'Accept' => 'application/json',
        ])->getJson("/api/v1/user/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_can_update_user_details()
    {
        $user = User::factory()->create();
        $updateData = UserMockData::getUpdateUserData();

        $updateData['id'] = $user->id;
        $updateData['is_active'] = true;

        $response = $this->withHeaders([
            'Authorization' => "Bearer $this->authToken",
            'Accept' => 'application/json',
        ])->patchJson("/api/v1/user/{$user->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', $updateData['name'])
            ->assertJsonPath('data.email', $updateData['email']);
    }

    public function test_can_delete_user()
    {
        $user = User::factory()->create();

        // Use Super Admin because Admin might not have delete permission
        $authData = generateSuperAdminUserAndAuthToken();
        $superAdminToken = $authData['token'];

        $response = $this->withHeaders([
            'Authorization' => "Bearer $superAdminToken",
            'Accept' => 'application/json',
        ])->deleteJson("/api/v1/user/{$user->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_can_assign_role_to_user()
    {
        $user = User::factory()->create();
        $roleId = UserRole::TENANT->id();

        $response = $this->withHeaders([
            'Authorization' => "Bearer $this->authToken",
            'Accept' => 'application/json',
        ])->postJson('/api/v1/user/assign-role', [
            'id' => $user->id,
            'roles' => [$roleId],
        ]);

        $response->assertStatus(200);
        expect($user->fresh()->roles->contains('id', $roleId))->toBeTrue();
    }

    public function test_can_change_user_password()
    {
        $user = User::factory()->create();
        $newPassword = 'newpassword123';

        $response = $this->withHeaders([
            'Authorization' => "Bearer $this->authToken",
            'Accept' => 'application/json',
        ])->postJson('/api/v1/user/change-password', [
            'user_id' => $user->id,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertStatus(200);
        expect(Hash::check($newPassword, $user->fresh()->password))->toBeTrue();
    }

    public function test_can_get_profile_data()
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer $this->authToken",
            'Accept' => 'application/json',
        ])->getJson('/api/v1/user/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $this->adminUser->id);
    }

    public function test_can_update_profile()
    {
        $updateData = UserMockData::getUpdateUserData();
        $updateData['email'] = 'adminupdated@example.com';
        $updateData['is_active'] = true; // Required by validation

        $response = $this->withHeaders([
            'Authorization' => "Bearer $this->authToken",
            'Accept' => 'application/json',
        ])->postJson('/api/v1/user/profile/update', $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.email', $updateData['email']);
    }

    public function test_can_change_profile_password()
    {
        // Default factory password is '123456'
        $currentPassword = '123456';
        $newPassword = 'newpassword123';

        $response = $this->withHeaders([
            'Authorization' => "Bearer $this->authToken",
            'Accept' => 'application/json',
        ])->postJson('/api/v1/user/profile/change-password', [
            'current_password' => $currentPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertStatus(200);
        expect(Hash::check($newPassword, $this->adminUser->fresh()->password))->toBeTrue();
    }

    public function test_can_update_profile_avatar()
    {
        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->withHeaders([
            'Authorization' => "Bearer $this->authToken",
            'Accept' => 'application/json',
        ])->postJson('/api/v1/user/profile/update-avatar', [
            'id' => $this->adminUser->id,
            'avatar' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $this->adminUser->id);
        // You might want to assert media attached if checking DB/Storage
    }

    public function test_can_export_user_data()
    {
        $authData = generateSuperAdminUserAndAuthToken();
        $superAdminToken = $authData['token'];

        $response = $this->withHeaders([
            'Authorization' => "Bearer $superAdminToken",
            'Accept' => 'application/json',
        ])->postJson('/api/v1/user/export');

        $response->assertStatus(200);
        // Assert content type or download headers
        expect($response->headers->get('content-type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_can_import_users_from_file()
    {
        $authData = generateSuperAdminUserAndAuthToken();
        $superAdminToken = $authData['token'];

        // Create a proper CSV file content with valid data
        $csvContent = "name,email,phone,password,status,roles\n";
        $csvContent .= "John Doe,john.unique@example.com,1234567890,password123,active,3\n";
        $csvContent .= 'Jane Smith,jane.unique@example.com,0987654321,password456,inactive,3';

        // Create a fake CSV file with proper content
        $file = UploadedFile::fake()->createWithContent('users.csv', $csvContent);

        $response = $this->withHeaders([
            'Authorization' => "Bearer $superAdminToken",
            'Accept' => 'application/json',
        ])->postJson('/api/v1/user/import', [
            'file' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'processed_rows',
                    'success_count',
                    'error_count',
                    'errors',
                ],
            ]);
    }

    public function test_can_import_users_in_background()
    {
        $authData = generateSuperAdminUserAndAuthToken();
        $superAdminToken = $authData['token'];

        // Create a proper CSV file content with valid data
        $csvContent = "name,email,phone,password,status,roles\n";
        $csvContent .= "John Doe,john.unique2@example.com,1234567890,password123,active,3\n";
        $csvContent .= 'Jane Smith,jane.unique2@example.com,0987654321,password456,inactive,3';
        $file = UploadedFile::fake()->createWithContent('users.csv', $csvContent);

        $response = $this->withHeaders([
            'Authorization' => "Bearer $superAdminToken",
            'Accept' => 'application/json',
        ])->postJson('/api/v1/user/import-background', [
            'file' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'job_id',
                    'status',
                    'message',
                ],
            ]);
    }

    public function test_can_export_users_in_background()
    {
        $authData = generateSuperAdminUserAndAuthToken();
        $superAdminToken = $authData['token'];

        $response = $this->withHeaders([
            'Authorization' => "Bearer $superAdminToken",
            'Accept' => 'application/json',
        ])->postJson('/api/v1/user/export-background');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'job_id',
                    'status',
                    'message',
                ],
            ]);
    }

    public function test_can_download_import_template()
    {
        $authData = generateSuperAdminUserAndAuthToken();
        $superAdminToken = $authData['token'];

        $response = $this->withHeaders([
            'Authorization' => "Bearer $superAdminToken",
            'Accept' => 'application/json',
        ])->postJson('/api/v1/user/import-template');

        $response->assertStatus(200);
        expect($response->headers->get('content-type'))->toBe('text/csv; charset=utf-8');
        expect($response->headers->get('content-disposition'))->toContain('attachment');
        expect($response->getContent())->toContain('name,email,phone,password,status,roles');
    }

    public function test_import_requires_valid_file()
    {
        $authData = generateSuperAdminUserAndAuthToken();
        $superAdminToken = $authData['token'];

        $response = $this->withHeaders([
            'Authorization' => "Bearer $superAdminToken",
            'Accept' => 'application/json',
        ])->postJson('/api/v1/user/import');

        $response->assertStatus(422);
    }

    public function test_import_background_requires_valid_file()
    {
        $authData = generateSuperAdminUserAndAuthToken();
        $superAdminToken = $authData['token'];

        $response = $this->withHeaders([
            'Authorization' => "Bearer $superAdminToken",
            'Accept' => 'application/json',
        ])->postJson('/api/v1/user/import-background');

        $response->assertStatus(422);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $authData = generateAdminUserAndAuthToken();
        $this->adminUser = $authData['user'];
        $this->authToken = $authData['token'];
    }
}
