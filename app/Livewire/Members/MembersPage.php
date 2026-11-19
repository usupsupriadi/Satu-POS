<?php

namespace App\Livewire\Members;

use App\Models\Member;
use App\Models\MemberRegion;
use App\Models\Region;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class MembersPage extends Component
{
    use WithPagination;

    public string $title = 'Member';

    public string $search = '';

    public bool $createMemberModalOpen = false;

    public string $name = '';

    public ?string $email = null;

    public ?string $phone = null;

    public ?string $province = null;

    public ?string $regency = null;

    public ?string $district = null;

    public ?string $points = null;

    public ?int $editingMemberId = null;

    public string $editingName = '';

    public ?string $editingEmail = null;

    public ?string $editingPhone = null;

    public ?string $editingProvince = null;

    public ?string $editingRegency = null;

    public ?string $editingDistrict = null;

    public ?string $editingPoints = null;

    public function mount(): void
    {
        $this->authorizePermission('members.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateMemberModal(): void
    {
        $this->authorizePermission('members.create');
        $this->authorizePermission('members.pii.view');

        $this->reset(['name', 'email', 'phone', 'province', 'regency', 'district', 'points']);
        $this->resetValidation();
        $this->createMemberModalOpen = true;
    }

    public function closeCreateMemberModal(): void
    {
        $this->createMemberModalOpen = false;
        $this->resetValidation();
    }

    public function updatedProvince(): void
    {
        $this->reset(['regency', 'district']);
    }

    public function updatedRegency(): void
    {
        $this->reset(['district']);
    }

    public function updatedEditingProvince(): void
    {
        $this->reset(['editingRegency', 'editingDistrict']);
    }

    public function updatedEditingRegency(): void
    {
        $this->reset(['editingDistrict']);
    }

    protected function membersQuery(): Builder
    {
        $canViewPii = $this->canViewPii();

        return Member::query()
            ->with('region')
            ->withCount('transactions')
            ->when($this->search !== '', function (Builder $query) use ($canViewPii): void {
                $term = '%'.$this->search.'%';
                $query->where(function (Builder $q) use ($term, $canViewPii): void {
                    $q->where('name', 'like', $term);

                    if ($canViewPii) {
                        $q->orWhere('email', 'like', $term)
                            ->orWhere('phone', 'like', $term);
                    }
                });
            })
            ->orderByDesc('id');
    }

    public function createMember(): void
    {
        $this->authorizePermission('members.create');
        $this->authorizePermission('members.pii.view');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('members', 'email')],
            'phone' => ['nullable', 'string', 'max:50', Rule::unique('members', 'phone')],
            'province' => ['nullable', 'string', 'max:255'],
            'regency' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'points' => ['nullable', 'integer', 'min:0'],
        ]);

        $location = $this->validatedLocation($validated);
        if ($location === null) {
            return;
        }

        Member::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'] !== '' ? $validated['email'] : null,
            'phone' => $validated['phone'] !== '' ? $validated['phone'] : null,
            'province' => $location['province'],
            'regency' => $location['regency'],
            'district' => $location['district'],
            'member_region_id' => $this->resolveTerritoryId($location),
            'points' => $validated['points'] === null || $validated['points'] === '' ? 0 : (int) $validated['points'],
        ]);

        $this->reset(['name', 'email', 'phone', 'province', 'regency', 'district', 'points']);
        $this->resetValidation();
        $this->resetPage();
        $this->createMemberModalOpen = false;
        $this->dispatch('toast', type: 'success', message: 'Member berhasil ditambahkan.');
    }

    public function startEditMember(int $memberId): void
    {
        $this->authorizePermission('members.edit');
        $this->authorizePermission('members.pii.view');

        $member = Member::query()->with('region')->findOrFail($memberId);

        $this->editingMemberId = (int) $member->id;
        $this->editingName = (string) $member->name;
        $this->editingEmail = $member->email;
        $this->editingPhone = $member->phone;
        $this->editingProvince = $member->province !== null ? (string) $member->province : ($member->region ? (string) $member->region->province : null);
        $this->editingRegency = $member->regency !== null ? (string) $member->regency : ($member->region ? (string) $member->region->regency : null);
        $this->editingDistrict = $member->district !== null ? (string) $member->district : ($member->region ? (string) $member->region->district : null);
        $this->editingPoints = (string) ((int) $member->points);
        $this->resetValidation();
    }

    public function cancelEditMember(): void
    {
        $this->editingMemberId = null;
        $this->reset(['editingName', 'editingEmail', 'editingPhone', 'editingProvince', 'editingRegency', 'editingDistrict', 'editingPoints']);
        $this->resetValidation();
    }

    public function updateMember(): void
    {
        $this->authorizePermission('members.edit');
        $this->authorizePermission('members.pii.view');

        if (! $this->editingMemberId) {
            return;
        }

        $validated = $this->validate([
            'editingName' => ['required', 'string', 'max:255'],
            'editingEmail' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('members', 'email')->ignore($this->editingMemberId),
            ],
            'editingPhone' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('members', 'phone')->ignore($this->editingMemberId),
            ],
            'editingProvince' => ['nullable', 'string', 'max:255'],
            'editingRegency' => ['nullable', 'string', 'max:255'],
            'editingDistrict' => ['nullable', 'string', 'max:255'],
            'editingPoints' => ['nullable', 'integer', 'min:0'],
        ]);

        $location = $this->validatedLocation([
            'province' => $validated['editingProvince'] ?? null,
            'regency' => $validated['editingRegency'] ?? null,
            'district' => $validated['editingDistrict'] ?? null,
        ], 'editingDistrict');
        if ($location === null) {
            return;
        }

        Member::query()
            ->whereKey($this->editingMemberId)
            ->update([
                'name' => $validated['editingName'],
                'email' => $validated['editingEmail'] !== '' ? $validated['editingEmail'] : null,
                'phone' => $validated['editingPhone'] !== '' ? $validated['editingPhone'] : null,
                'province' => $location['province'],
                'regency' => $location['regency'],
                'district' => $location['district'],
                'member_region_id' => $this->resolveTerritoryId($location),
                'points' => $validated['editingPoints'] === null || $validated['editingPoints'] === '' ? 0 : (int) $validated['editingPoints'],
            ]);

        $this->cancelEditMember();
    }

    public function deleteMember(int $memberId): void
    {
        $this->authorizePermission('members.delete');

        $member = Member::query()->withCount('transactions')->findOrFail($memberId);

        if ((int) $member->transactions_count > 0) {
            $this->addError('members', 'Member tidak bisa dihapus karena sudah digunakan pada transaksi.');

            return;
        }

        $member->delete();

        if ($this->editingMemberId === $memberId) {
            $this->cancelEditMember();
        }
    }

    public function render(): View
    {
        $this->authorizePermission('members.view');

        $members = $this->membersQuery()->paginate(15);
        $provinces = Region::provinceNames();
        $regencies = Region::regencyNames($this->province);
        $districts = Region::districtNames($this->province, $this->regency);
        $editingRegencies = Region::regencyNames($this->editingProvince);
        $editingDistricts = Region::districtNames($this->editingProvince, $this->editingRegency);

        return view('livewire.members.members-page', [
            'members' => $members,
            'provinces' => $provinces,
            'regencies' => $regencies,
            'districts' => $districts,
            'editingRegencies' => $editingRegencies,
            'editingDistricts' => $editingDistricts,
        ])->layout('layouts.app', ['title' => $this->title]);
    }

    /**
     * Validasi kombinasi wilayah; mengembalikan alamat ternormalisasi atau null bila tidak valid.
     *
     * @param  array<string, mixed>  $data
     * @return array{province: ?string, regency: ?string, district: ?string}|null
     */
    private function validatedLocation(array $data, string $errorKey = 'district'): ?array
    {
        $province = trim((string) ($data['province'] ?? ''));
        $regency = trim((string) ($data['regency'] ?? ''));
        $district = trim((string) ($data['district'] ?? ''));

        $filled = count(array_filter([$province, $regency, $district], fn ($value) => $value !== ''));

        if ($filled === 0) {
            return ['province' => null, 'regency' => null, 'district' => null];
        }

        if ($filled < 3 || ! Region::isValidCombination($province, $regency, $district)) {
            $this->addError($errorKey, 'Wilayah tidak valid. Pilih provinsi, kabupaten/kota, dan kecamatan yang sesuai.');

            return null;
        }

        return ['province' => $province, 'regency' => $regency, 'district' => $district];
    }

    /**
     * @param  array{province: ?string, regency: ?string, district: ?string}  $location
     */
    private function resolveTerritoryId(array $location): ?int
    {
        if ($location['province'] === null || $location['regency'] === null || $location['district'] === null) {
            return null;
        }

        return MemberRegion::query()
            ->where('province', $location['province'])
            ->where('regency', $location['regency'])
            ->where('district', $location['district'])
            ->value('id');
    }

    private function canViewPii(): bool
    {
        $user = auth()->user();

        return (bool) (($user && method_exists($user, 'can')) ? $user->can('members.pii.view') : false);
    }

    private function authorizePermission(string $permission): void
    {
        $permission = trim($permission);
        if ($permission === '') {
            abort(403);
        }

        $user = auth()->user();
        if (! $user || ! method_exists($user, 'can') || ! $user->can($permission)) {
            abort(403);
        }
    }
}
