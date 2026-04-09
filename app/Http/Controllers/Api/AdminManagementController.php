<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminManagementController extends Controller
{
    /**
     * @return array<int, string>
     */
    private function manageableStaffRoles(): array
    {
        return [
            UserRole::Admin->value,
            UserRole::Accountant->value,
            UserRole::Staff->value,
            UserRole::Cashier->value,
            UserRole::Technician->value,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function supportedIntakeCategories(): array
    {
        return ['PANEL', 'Screen broken', 'LED', 'M.B'];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformStaff(User $user, ?string $generatedPassword = null): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roleEnum()->value,
            'can_record_payment' => $user->canRecordPaymentPermission(),
            'is_active' => (bool) $user->is_active,
            'generated_password' => $generatedPassword,
        ];
    }

    private function authorizeAdmin(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();

        abort_unless($user && $user->roleEnum() === UserRole::Admin, 403, 'Only admin can access this endpoint.');
    }

    public function staffIndex(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $staff = User::query()
            ->whereIn('role', $this->manageableStaffRoles())
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'can_record_payment', 'is_active'])
            ->map(fn (User $user): array => $this->transformStaff($user))
            ->values();

        return response()->json(['staff' => $staff]);
    }

    public function createStaff(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in($this->manageableStaffRoles())],
            'can_record_payment' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $password = $payload['password'] ?? Str::password(length: 14, letters: true, numbers: true, symbols: false, spaces: false);

        $user = User::create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => Hash::make($password),
            'role' => $payload['role'],
            'can_record_payment' => (bool) ($payload['can_record_payment'] ?? false),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Staff member created successfully.',
            'staff' => $this->transformStaff($user, isset($payload['password']) ? null : $password),
        ], 201);
    }

    public function removeStaff(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        abort_if((int) $request->user()?->id === (int) $user->id, 422, 'Admin cannot remove themselves.');

        $user->is_active = false;
        $user->can_record_payment = false;
        $user->save();

        $hiddenIds = AppSetting::getValue('staff.hidden_ids', []);
        if (is_array($hiddenIds)) {
            AppSetting::setValue('staff.hidden_ids', array_values(array_filter($hiddenIds, fn ($id): bool => (string) $id !== (string) $user->id)));
        }

        return response()->json([
            'message' => 'Staff member removed from active operations.',
        ]);
    }

    public function restoreStaff(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        $user->is_active = true;
        $user->save();

        return response()->json([
            'message' => 'Staff member restored.',
        ]);
    }

    public function resetStaffPassword(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        $password = Str::password(length: 14, letters: true, numbers: true, symbols: false, spaces: false);

        $user->password = Hash::make($password);
        $user->save();

        return response()->json([
            'message' => 'Staff password reset successfully.',
            'staff' => $this->transformStaff($user, $password),
        ]);
    }

    public function updateSections(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $payload = $request->validate([
            'sections' => ['required', 'array'],
            'sections.*' => ['boolean'],
        ]);

        AppSetting::setValue('ui.sections', $payload['sections']);

        return response()->json([
            'message' => 'Section visibility updated.',
            'sections' => AppSetting::getValue('ui.sections', []),
        ]);
    }

    public function updateIntakeForm(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $payload = $request->validate([
            'intake_form' => ['required', 'array'],
            'intake_form.customerNameLabel' => ['nullable', 'string', 'max:120'],
            'intake_form.customerPhoneLabel' => ['nullable', 'string', 'max:120'],
            'intake_form.tvModelLabel' => ['nullable', 'string', 'max:120'],
            'intake_form.issueLabel' => ['nullable', 'string', 'max:160'],
            'intake_form.estimatedPriceLabel' => ['nullable', 'string', 'max:120'],
            'intake_form.defaultCategory' => ['nullable', Rule::in($this->supportedIntakeCategories())],
            'intake_form.defaultPriority' => ['nullable', Rule::in(['normal', 'high'])],
        ]);

        AppSetting::setValue('ui.intake_form', $payload['intake_form']);

        return response()->json([
            'message' => 'Intake form customization updated.',
            'intake_form' => AppSetting::getValue('ui.intake_form', []),
        ]);
    }

    public function updateHiddenStaffIds(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $payload = $request->validate([
            'hidden_staff_ids' => ['required', 'array'],
            'hidden_staff_ids.*' => ['string'],
        ]);

        AppSetting::setValue('staff.hidden_ids', array_values(array_unique($payload['hidden_staff_ids'])));

        return response()->json([
            'message' => 'Hidden staff list updated.',
            'hidden_staff_ids' => AppSetting::getValue('staff.hidden_ids', []),
        ]);
    }
}
