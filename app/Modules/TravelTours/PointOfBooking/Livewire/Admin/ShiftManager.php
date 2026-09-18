<?php

/** Manager workspace for registers and shift reconciliation. */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Livewire\Admin;

use App\Modules\TravelTours\PointOfBooking\Exceptions\PointOfBookingException;
use App\Modules\TravelTours\PointOfBooking\Models\BookingRegister;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShift;
use App\Modules\TravelTours\PointOfBooking\Services\BookingShiftService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Registers are the physical desks a shift runs on; they are created here
 * and switched off rather than deleted. Shifts are listed with their expected,
 * counted, and variance figures so a manager can reconcile closed ones.
 */
final class ShiftManager extends Component
{
    use WithPagination;

    private const PER_PAGE = 20;

    #[Locked]
    public ?int $registerId = null;

    #[Locked]
    public ?int $shiftId = null;

    public string $dialog = '';

    public string $code = '';

    public string $name = '';

    public string $location = '';

    public string $paperWidth = '80';

    public bool $automaticPrint = false;

    public bool $isActive = true;

    public string $notes = '';

    /** Require shift management on each Livewire request. */
    public function boot(): void
    {
        Gate::authorize('viewAny', BookingShift::class);
    }

    /**
     * Every register with its open shift, if any.
     *
     * @return Collection<int, BookingRegister>
     */
    #[Computed]
    public function registers(): Collection
    {
        return BookingRegister::query()->with(['shifts' => fn ($query) => $query->open()->with('operator')])->orderBy('name')->get();
    }

    /**
     * Shifts newest first with their figures.
     *
     * @return LengthAwarePaginator<int, BookingShift>
     */
    #[Computed]
    public function shifts(): LengthAwarePaginator
    {
        return BookingShift::query()->with(['register', 'operator', 'reconciler'])->withCount('bookings')->orderByDesc('opened_at')->paginate(self::PER_PAGE);
    }

    /** Open the register dialog for a new register. */
    public function openCreate(): void
    {
        Gate::authorize('create', BookingRegister::class);
        $this->registerId = null;
        $this->reset('code', 'name', 'location', 'paperWidth', 'automaticPrint', 'isActive');
        $this->open('register');
    }

    /** Open the register dialog for an existing register. */
    public function openEdit(int $registerId): void
    {
        $register = BookingRegister::query()->findOrFail($registerId);
        Gate::authorize('update', $register);
        $this->registerId = $register->getKey();
        $this->code = $register->code;
        $this->name = $register->name;
        $this->location = (string) $register->location;
        $this->paperWidth = (string) ($register->receipt_paper_width_mm ?: 80);
        $this->automaticPrint = (bool) $register->automatic_receipt_print;
        $this->isActive = (bool) $register->is_active;
        $this->open('register');
    }

    /** Create or update a register. */
    public function saveRegister(): void
    {
        $register = $this->registerId === null ? new BookingRegister : BookingRegister::query()->findOrFail($this->registerId);
        Gate::authorize($register->exists ? 'update' : 'create', $register->exists ? $register : BookingRegister::class);
        $this->validate([
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9][A-Za-z0-9-]*$/', 'unique:travel_booking_registers,code,'.($this->registerId ?? 'NULL').',id'],
            'name' => ['required', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:160'],
            'paperWidth' => ['required', 'in:58,80'],
            'automaticPrint' => ['boolean'],
            'isActive' => ['boolean'],
        ], ['code.regex' => 'Codes use letters, numbers, and hyphens.', 'code.unique' => 'Another register already uses this code.']);

        if ($register->exists && ! $this->isActive && $register->shifts()->open()->exists()) {
            $this->addError('management', 'Close the open shift on this register before deactivating it.');

            return;
        }

        $register->forceFill([
            'code' => strtoupper(trim($this->code)),
            'name' => trim($this->name),
            'location' => trim($this->location) === '' ? null : trim($this->location),
            'is_active' => $this->isActive,
            'receipt_printer_driver' => 'browser',
            'receipt_paper_width_mm' => (int) $this->paperWidth,
            'automatic_receipt_print' => $this->automaticPrint,
            'updated_by' => auth()->id(),
        ] + ($register->exists ? [] : ['created_by' => auth()->id()]))->save();

        session()->flash('success', $this->registerId === null ? 'Register created.' : 'Register updated.');
        $this->closeDialog();
    }

    /** Open the reconciliation dialog for a closed shift. */
    public function openReconcile(int $shiftId): void
    {
        $shift = BookingShift::query()->findOrFail($shiftId);
        Gate::authorize('update', $shift);
        $this->shiftId = $shift->getKey();
        $this->notes = '';
        $this->open('reconcile');
    }

    /** Sign off a closed shift. */
    public function reconcile(BookingShiftService $shifts): void
    {
        $shift = BookingShift::query()->findOrFail((int) $this->shiftId);
        Gate::authorize('update', $shift);
        $this->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        try {
            $shifts->reconcile($shift, $this->notes, (int) auth()->id());
        } catch (PointOfBookingException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        session()->flash('success', 'Shift reconciled.');
        $this->closeDialog();
    }

    /** Close any dialog. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->registerId = null;
        $this->shiftId = null;
        $this->resetErrorBag();
        unset($this->registers, $this->shifts);
    }

    /** Render the workspace. */
    public function render(): View
    {
        return view('travel-tours::livewire.admin.pob.shift-manager', ['selectedShift' => $this->shiftId === null ? null : BookingShift::query()->with(['register', 'operator'])->find($this->shiftId)]);
    }

    /** Open a dialog with a clean error bag. */
    private function open(string $dialog): void
    {
        $this->dialog = $dialog;
        $this->resetErrorBag();
    }
}
