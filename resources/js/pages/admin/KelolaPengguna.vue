<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Skeleton from 'primevue/skeleton';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';
import api, { csrf, pesanGalat } from '../../api';
import { useAuth } from '../../stores/auth';

const auth = useAuth();
const toast = useToast();

const daftar = ref([]);
const memuat = ref(true);

const saring = reactive({ role: '', status_akun: '', q: '' });

const pilihanPeran = [
    { label: 'Semua peran', nilai: '' },
    { label: 'Calon Penyewa', nilai: 'penyewa' },
    { label: 'Pemilik Kos', nilai: 'pemilik' },
    { label: 'Admin', nilai: 'admin' },
];

const pilihanStatus = [
    { label: 'Semua status', nilai: '' },
    { label: 'Aktif', nilai: 'aktif' },
    { label: 'Nonaktif', nilai: 'nonaktif' },
];

async function muat() {
    memuat.value = true;

    try {
        const { data } = await api.get('/admin/pengguna', {
            params: {
                role: saring.role || undefined,
                status_akun: saring.status_akun || undefined,
                q: saring.q || undefined,
            },
        });

        daftar.value = data.data;
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Daftar pengguna gagal dimuat',
            detail: pesanGalat(error),
            life: 8000,
        });
    } finally {
        memuat.value = false;
    }
}

/*
 * Penonaktifan selalu lewat dialog, tidak pernah satu klik.
 *
 * Tindakan ini mengeluarkan orang dari sistem seketika dan alasannya wajib
 * ditulis. Tombol yang langsung bekerja akan membuat kesalahan klik berakibat
 * seseorang kehilangan akses tanpa catatan apa pun.
 */
const dialogTerbuka = ref(false);
const targetNonaktif = ref(null);
const alasan = ref('');
const mengirim = ref(false);

function bukaDialog(pengguna) {
    targetNonaktif.value = pengguna;
    alasan.value = '';
    dialogTerbuka.value = true;
}

async function ubahStatus(pengguna, status, alasanTeks = null) {
    mengirim.value = true;

    try {
        await csrf();
        const { data } = await api.post(`/admin/pengguna/${pengguna.id}/status`, {
            status_akun: status,
            alasan: alasanTeks ?? undefined,
        });

        // Baris diperbarui di tempat, bukan dengan memuat ulang seluruh
        // daftar: pada halaman yang sedang disaring, memuat ulang bisa
        // membuat baris yang baru saja diubah menghilang dari layar dan
        // tampak seperti kegagalan.
        const urutan = daftar.value.findIndex((satu) => satu.id === pengguna.id);

        if (urutan !== -1) {
            daftar.value[urutan] = data.data;
        }

        dialogTerbuka.value = false;

        toast.add({
            severity: 'success',
            summary: status === 'nonaktif' ? 'Akun dinonaktifkan' : 'Akun diaktifkan',
            detail: `${pengguna.nama} sekarang berstatus ${status}.`,
            life: 5000,
        });
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Perubahan gagal',
            detail: pesanGalat(error),
            life: 9000,
        });
    } finally {
        mengirim.value = false;
    }
}

/**
 * Aturan yang sama dengan UserPolicy::kelolaStatus di server.
 *
 * Ini hanya untuk tampilan — yang benar-benar menolak adalah Policy. Tombol
 * yang tampil lalu ditolak server akan terasa seperti sistem yang rusak,
 * sehingga tombolnya tidak ditampilkan sejak awal.
 */
function bolehDiubah(pengguna) {
    return pengguna.id !== auth.pengguna?.id && pengguna.role !== 'admin';
}

function waktu(iso) {
    return iso ? new Date(iso).toLocaleDateString('id-ID', { dateStyle: 'medium' }) : '-';
}

let penunda = null;

watch(() => saring.q, () => {
    clearTimeout(penunda);
    penunda = setTimeout(muat, 500);
});

onMounted(muat);
</script>

<template>
    <div class="mx-auto max-w-5xl">
        <header v-muncul:naik class="mb-5">
            <h1 class="font-display text-3xl font-extrabold tracking-tight">Kelola Pengguna</h1>
            <p class="mt-1 text-sm text-[color:var(--color-tinta)]/65">
                Nonaktifkan akun yang melanggar aturan. Akun nonaktif langsung kehilangan
                akses pada permintaan berikutnya, bukan menunggu sesinya habis.
            </p>
        </header>

        <!--
            Peran tidak dapat diubah dari halaman ini, dan itu disengaja.
            Kenaikan menjadi pemilik kos punya jalurnya sendiri lewat pengajuan
            berkas dan verifikasi; pintu kedua yang melewati pemeriksaan KTP
            akan membuat jalur itu sia-sia.
        -->
        <Message v-muncul="{ nama: 'naik', jeda: 80 }" severity="secondary" :closable="false" class="mb-5">
            Perubahan peran menjadi Pemilik Kos dilakukan lewat
            <RouterLink :to="{ name: 'admin-pengajuan' }" class="font-semibold underline">
                Verifikasi Pengajuan
            </RouterLink>, bukan dari sini.
        </Message>

        <section v-muncul="{ nama: 'naik', jeda: 140 }" class="rounded-xl border border-[color:var(--color-garis)] bg-white p-4">
            <div class="grid gap-3 sm:grid-cols-[1fr_auto_auto]">
                <IconField>
                    <InputIcon class="pi pi-search" />
                    <InputText
                        v-model="saring.q"
                        placeholder="Cari nama atau email"
                        class="w-full"
                        aria-label="Cari pengguna"
                    />
                </IconField>

                <select
                    v-model="saring.role"
                    class="rounded-lg border border-[color:var(--color-garis)] bg-white px-3 py-2 text-sm"
                    aria-label="Saring peran"
                    @change="muat"
                >
                    <option v-for="pilihan in pilihanPeran" :key="pilihan.label" :value="pilihan.nilai">
                        {{ pilihan.label }}
                    </option>
                </select>

                <select
                    v-model="saring.status_akun"
                    class="rounded-lg border border-[color:var(--color-garis)] bg-white px-3 py-2 text-sm"
                    aria-label="Saring status"
                    @change="muat"
                >
                    <option v-for="pilihan in pilihanStatus" :key="pilihan.label" :value="pilihan.nilai">
                        {{ pilihan.label }}
                    </option>
                </select>
            </div>
        </section>

        <div v-if="memuat" class="mt-4 space-y-3">
            <Skeleton v-for="n in 5" :key="n" height="4.5rem" border-radius="0.75rem" />
        </div>

        <p
            v-else-if="daftar.length === 0"
            v-muncul:mekar
            class="mt-4 rounded-xl border border-[color:var(--color-garis)] bg-white p-8 text-center text-sm text-[color:var(--color-tinta)]/60"
        >
            Tidak ada pengguna yang cocok dengan penyaring ini.
        </p>

        <ul v-else class="mt-4 space-y-3">
            <li
                v-for="(pengguna, urutan) in daftar"
                :key="pengguna.id"
                v-muncul="{ nama: 'naik', jeda: Math.min(urutan, 7) * 60 }"
                class="rounded-xl border border-[color:var(--color-garis)] bg-white p-4"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold">{{ pengguna.nama }}</p>
                            <Tag :value="pengguna.role_label" severity="secondary" />
                            <Tag
                                :value="pengguna.status_akun_label"
                                :severity="pengguna.status_akun === 'aktif' ? 'success' : 'danger'"
                            />
                            <span
                                v-if="pengguna.id === auth.pengguna?.id"
                                class="rounded bg-[color:var(--color-kertas)] px-2 py-0.5 text-xs font-medium"
                            >
                                Anda
                            </span>
                        </div>

                        <p class="mt-1 truncate text-sm text-[color:var(--color-tinta)]/70">
                            {{ pengguna.email }}
                            <span v-if="pengguna.no_hp"> &middot; {{ pengguna.no_hp }}</span>
                        </p>
                        <p class="mt-0.5 text-xs text-[color:var(--color-tinta)]/50">
                            Terdaftar {{ waktu(pengguna.terdaftar_pada) }}
                        </p>

                        <p
                            v-if="pengguna.alasan_nonaktif"
                            class="mt-2 rounded-lg bg-[color:var(--color-maroon)]/8 px-3 py-2 text-sm text-[color:var(--color-maroon)]"
                        >
                            {{ pengguna.alasan_nonaktif }}
                        </p>
                    </div>

                    <div v-if="bolehDiubah(pengguna)" class="shrink-0">
                        <Button
                            v-if="pengguna.status_akun === 'aktif'"
                            label="Nonaktifkan"
                            severity="danger"
                            outlined
                            size="small"
                            @click="bukaDialog(pengguna)"
                        />
                        <Button
                            v-else
                            label="Aktifkan"
                            severity="success"
                            size="small"
                            :loading="mengirim"
                            @click="ubahStatus(pengguna, 'aktif')"
                        />
                    </div>

                    <p v-else class="shrink-0 self-center text-xs text-[color:var(--color-tinta)]/45">
                        Tidak dapat diubah
                    </p>
                </div>
            </li>
        </ul>

        <Dialog
            v-model:visible="dialogTerbuka"
            modal
            header="Nonaktifkan akun"
            class="w-full max-w-md"
        >
            <p class="text-sm text-[color:var(--color-tinta)]/75">
                <strong>{{ targetNonaktif?.nama }}</strong> akan langsung kehilangan akses ke
                sistem. Pesanan dan data kosnya tidak dihapus.
            </p>

            <label for="alasan" class="mt-4 mb-1.5 block text-sm font-medium">
                Alasan penonaktifan
            </label>
            <Textarea
                id="alasan"
                v-model="alasan"
                rows="3"
                class="w-full"
                placeholder="Contoh: memasang iklan kos yang tidak ada"
            />
            <p class="mt-1 text-xs text-[color:var(--color-tinta)]/55">
                Disimpan agar Anda dapat menjawab bila yang bersangkutan bertanya nanti.
            </p>

            <template #footer>
                <Button label="Batal" severity="secondary" text @click="dialogTerbuka = false" />
                <Button
                    label="Nonaktifkan"
                    severity="danger"
                    :loading="mengirim"
                    :disabled="alasan.trim().length === 0"
                    @click="ubahStatus(targetNonaktif, 'nonaktif', alasan)"
                />
            </template>
        </Dialog>
    </div>
</template>
