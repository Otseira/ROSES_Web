<div>
    <label class="block text-sm font-bold text-slate-700 mb-2">Hak Akses Sistem</label>
    <select name="role" id="role_select" required
        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-accent/20 focus:border-accent outline-none transition-all text-sm font-bold text-slate-700">
        <option value="staf" {{ old('role', $master_pegawai->role) == 'staf' ? 'selected' : '' }}>Staf / Karyawan
            Pelaksana</option>
        <option value="kepala_unit" {{ old('role', $master_pegawai->role) == 'kepala_unit' ? 'selected' : '' }}>Kepala
            Unit Kerja</option>
        <option value="penanggung_jawab" {{ old('role', $master_pegawai->role) == 'penanggung_jawab' ? 'selected' : ''
            }}>Penanggung Jawab Shift</option>
        <option value="manajer" {{ old('role', $master_pegawai->role) == 'manajer' ? 'selected' : '' }}>Manajer
            (Multi-Unit)</option>
        <option value="direktur" {{ old('role', $master_pegawai->role) == 'direktur' ? 'selected' : '' }}>Direktur
        </option>
        <option value="hrd" {{ old('role', $master_pegawai->role) == 'hrd' ? 'selected' : '' }}>HRD / Payroll</option>
        @if(auth()->user()->role === 'superadmin' || $master_pegawai->role === 'superadmin')
        <option value="superadmin" {{ old('role', $master_pegawai->role) == 'superadmin' ? 'selected' : ''
            }}>Administrator</option>
        @endif
    </select>
    @error('role') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
</div>

<!-- Container Unit Kelola (Sama seperti di create, tapi cek apakah sudah di-manage) -->
<div id="unit_kelola_container" class="hidden md:col-span-2 bg-blue-50/50 border border-blue-100 rounded-2xl p-6">
    <div class="flex items-center gap-3 mb-4">
        <div class="w-8 h-8 bg-blue-500 text-white rounded-lg flex items-center justify-center">
            <i data-lucide="building-2" class="w-4 h-4"></i>
        </div>
        <div>
            <h4 class="font-bold text-blue-900 text-sm">Unit Kerja yang Dikelola</h4>
            <p class="text-xs text-blue-600 font-medium">Pilih satu atau lebih unit yang akan diawasi oleh pegawai ini.
            </p>
        </div>
    </div>

    <div
        class="grid grid-cols-2 md:grid-cols-3 gap-3 max-h-60 overflow-y-auto p-2 bg-white rounded-xl border border-blue-200">
        @foreach($unitKerja as $unit)
        <label
            class="flex items-center gap-2 p-3 bg-slate-50 hover:bg-blue-50 rounded-lg cursor-pointer transition-all border border-transparent hover:border-blue-200">
            <input type="checkbox" name="manages_units[]" value="{{ $unit->id }}"
                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500" {{
                in_array($unit->id, old('manages_units', $master_pegawai->managesUnits->pluck('id')->toArray())) ?
            'checked' : '' }}>
            <span class="text-sm font-semibold text-slate-700">{{ $unit->nama_unit }}</span>
        </label>
        @endforeach
    </div>
</div>

<!-- ... existing buttons ... -->

<!-- Tambahkan script yang sama persis seperti di create.blade.php di bagian bawah -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const roleSelect = document.getElementById('role_select');
        const unitKelolaContainer = document.getElementById('unit_kelola_container');

        const rolesWithUnitManagement = ['kepala_unit', 'penanggung_jawab', 'manajer'];

        function toggleUnitKelola() {
            if (rolesWithUnitManagement.includes(roleSelect.value)) {
                unitKelolaContainer.classList.remove('hidden');
            } else {
                unitKelolaContainer.classList.add('hidden');
            }
        }

        roleSelect.addEventListener('change', toggleUnitKelola);
        toggleUnitKelola();

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
</script>