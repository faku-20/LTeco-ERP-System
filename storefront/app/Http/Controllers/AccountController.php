<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CustomerAddress;
use App\Models\CustomerProfile;
use App\Rules\UruguayCedula;
use App\Rules\UruguayRut;
use App\Services\SecurityAuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AccountController extends Controller
{
    public function __construct(private readonly SecurityAuditLogger $audit) {}

    public function show(Request $request): View
    {
        $user = $request->user()->load(['profile', 'addresses']);

        return view('account.dashboard', [
            'user' => $user,
            'orders' => $user->orders()->with('items')->latest()->paginate(15),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'last_name' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'customer_type' => ['required', Rule::in(['consumer', 'business'])],
            'legal_name' => [
                'exclude_unless:customer_type,business',
                'required',
                'string',
                'max:190',
                'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
            ],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\s.-]{6,30}$/'],
            'cedula' => ['exclude_unless:customer_type,consumer', 'required', new UruguayCedula],
            'rut' => ['exclude_unless:customer_type,business', 'required', new UruguayRut],
        ]);

        $customerType = (string) $validated['customer_type'];
        $cedula = $customerType === 'consumer'
            ? (preg_replace('/\D+/', '', (string) $validated['cedula']) ?: null)
            : null;
        $rut = $customerType === 'business'
            ? (preg_replace('/\D+/', '', (string) $validated['rut']) ?: null)
            : null;
        $legalName = $customerType === 'business'
            ? Str::squish((string) $validated['legal_name'])
            : null;
        $phone = Str::squish((string) $validated['phone']);
        $blindKey = (string) config('storefront_privacy.blind_index_key');

        if ($blindKey === '') {
            throw new \RuntimeException('La protección de datos no está configurada.');
        }

        $normalizedEmail = Str::lower(trim((string) $validated['email']));
        $emailChanged = $normalizedEmail !== $user->email;

        try {
            DB::transaction(function () use (
                $user,
                $validated,
                $customerType,
                $legalName,
                $phone,
                $cedula,
                $rut,
                $blindKey,
                $normalizedEmail,
                $emailChanged,
            ): void {
                $user->forceFill([
                    'first_name' => Str::squish((string) $validated['first_name']),
                    'last_name' => Str::squish((string) $validated['last_name']),
                    'email' => $normalizedEmail,
                    'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
                ])->save();

                CustomerProfile::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'customer_type' => $customerType,
                        'legal_name' => $legalName,
                        'phone_encrypted' => $phone,
                        'cedula_encrypted' => $cedula,
                        'cedula_blind_index' => $cedula ? hash_hmac('sha256', $cedula, $blindKey) : null,
                        'rut_encrypted' => $rut,
                        'rut_blind_index' => $rut ? hash_hmac('sha256', $rut, $blindKey) : null,
                        'status' => 'active',
                    ],
                );
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw ValidationException::withMessages([
                    'cedula' => 'La cédula, RUT o correo ya está asociado a otra cuenta.',
                ]);
            }

            throw $exception;
        }

        $this->audit->record(
            $request,
            'account.profile_updated',
            'user',
            $user->public_uuid,
            ['changed_fields' => ['name', 'email', 'customer_type', 'billing_identity', 'phone']],
            $user,
        );

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice')->with('status', 'verification-link-sent');
        }

        return back()->with('status', 'Actualizamos tus datos.');
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $data = $this->addressData($request);
        if (! $request->user()->addresses()->where('type', 'billing')->exists()) {
            $data['is_primary'] = true;
        }

        DB::transaction(function () use ($request, $data): void {
            if ($data['is_primary']) {
                CustomerAddress::query()
                    ->where('user_id', $request->user()->id)
                    ->where('type', 'billing')
                    ->update(['is_primary' => false]);
            }

            CustomerAddress::query()->create([
                'user_id' => $request->user()->id,
                ...$data,
            ]);
        });

        $this->audit->record(
            $request,
            'account.address_created',
            'address',
            null,
            ['result' => 'created'],
            $request->user(),
        );

        return back()->with('status', 'Guardamos la dirección.');
    }

    public function updateAddress(Request $request, CustomerAddress $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        $data = $this->addressData($request);

        DB::transaction(function () use ($request, $address, $data): void {
            if ($data['is_primary']) {
                CustomerAddress::query()
                    ->where('user_id', $request->user()->id)
                    ->where('type', 'billing')
                    ->where('id', '<>', $address->id)
                    ->update(['is_primary' => false]);
            }

            $address->update($data);
        });

        $this->audit->record(
            $request,
            'account.address_updated',
            'address',
            (string) $address->id,
            ['address_id' => $address->id],
            $request->user(),
        );

        return back()->with('status', 'Actualizamos la dirección.');
    }

    public function destroyAddress(Request $request, CustomerAddress $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);

        if ($address->is_primary && $request->user()->addresses()->where('type', 'billing')->count() > 1) {
            throw ValidationException::withMessages([
                'address' => 'Elegí otra dirección principal antes de eliminar esta.',
            ]);
        }

        $addressId = $address->id;
        $address->delete();
        $this->audit->record(
            $request,
            'account.address_deleted',
            'address',
            (string) $addressId,
            ['address_id' => $addressId],
            $request->user(),
        );

        return back()->with('status', 'Eliminamos la dirección.');
    }

    public function password(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                'confirmed',
                Password::min(10)->letters()->mixedCase()->numbers(),
            ],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make((string) $validated['password']),
            'remember_token' => Str::random(60),
        ])->save();
        $request->session()->regenerate();

        $this->audit->record(
            $request,
            'account.password_changed',
            'user',
            $request->user()->public_uuid,
            ['result' => 'changed'],
            $request->user(),
        );

        return back()->with('status', 'Actualizamos tu contraseña.');
    }

    /** @return array<string,mixed> */
    private function addressData(Request $request): array
    {
        $validated = $request->validate([
            'line1' => ['required', 'string', 'max:190', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'line2' => ['nullable', 'string', 'max:190', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'city' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'department' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'postal_code' => ['nullable', 'string', 'max:20', 'regex:/^[0-9A-Za-z\s-]*$/'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        return [
            'type' => 'billing',
            'line1_encrypted' => Str::squish((string) $validated['line1']),
            'line2_encrypted' => isset($validated['line2'])
                ? Str::squish((string) $validated['line2'])
                : null,
            'city_encrypted' => Str::squish((string) $validated['city']),
            'department_encrypted' => Str::squish((string) $validated['department']),
            'postal_code_encrypted' => isset($validated['postal_code'])
                ? trim((string) $validated['postal_code'])
                : null,
            'country' => 'UY',
            'is_primary' => $request->boolean('is_primary'),
        ];
    }
}
