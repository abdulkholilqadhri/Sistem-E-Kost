<script setup>
import { onMounted, ref } from 'vue';
import Skeleton from 'primevue/skeleton';
import { useToast } from 'primevue/usetoast';
import api, { pesanGalat } from '../api';
import AngkaBerjalan from '../components/AngkaBerjalan.vue';

const toast = useToast();

const data = ref(null);
const memuat = ref(true);

async function muat() {
    memuat.value = true;

    try {
        const { data: jawaban } = await api.get('/dashboard');
        data.value = jawaban.data;
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Dashboard gagal dimuat',
            detail: pesanGalat(error),
            life: 8000,
        });
    } finally {
        memuat.value = false;
    }
}

function waktu(iso) {
    if (!iso) {
        return '-';
    }

    return new Date(iso).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}

onMounted(muat);
</script>

<template>
    <div>
        <header v-muncul:naik class="mb-6">
            <h1 class="font-display text-3xl font-extrabold tracking-tight">
                <template v-if="data">Halo, {{ data.nama }}</template>
                <template v-else>Dashboard</template>
            </h1>
            <p class="mt-1 text-sm text-[color:var(--color-tinta)]/65">
                Ringkasan yang perlu Anda ketahui hari ini.
            </p>
        </header>

        <!-- Rangka tiruan memakai bentuk kartu yang sama supaya tata letak
             tidak melompat saat angkanya datang. -->
        <div v-if="memuat" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="n in 4"
                :key="n"
                class="rounded-xl border border-[color:var(--color-garis)] bg-white p-5"
            >
                <Skeleton width="55%" height="0.8rem" />
                <Skeleton width="40%" height="2.4rem" class="mt-3" />
            </div>
        </div>

        <template v-else-if="data">
            <!--
                Kartu statistik, mengikuti mockup dasbor admin SDD hal. 62.
                Angkanya berjalan naik memakai komponen yang sama dengan
                jumlah hasil pencarian di beranda.
            -->
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan">
                <article
                    v-for="(butir, urutan) in data.ringkasan"
                    :key="butir.label"
                    v-muncul="{ nama: 'mekar', jeda: urutan * 70 }"
                    class="rounded-xl border bg-white p-5 transition"
                    :class="butir.mendesak
                        ? 'border-[color:var(--color-emas)] shadow-[0_12px_28px_-18px_rgba(200,146,42,0.65)]'
                        : 'border-[color:var(--color-garis)]'"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm text-[color:var(--color-tinta)]/65">{{ butir.label }}</p>
                        <i
                            :class="`pi ${butir.ikon} text-sm`"
                            :style="{ color: butir.mendesak ? 'var(--color-emas)' : 'var(--color-tinta)' }"
                            aria-hidden="true"
                        />
                    </div>
                    <p class="font-display mt-2 text-4xl leading-none font-extrabold">
                        <AngkaBerjalan :nilai="butir.nilai" />
                    </p>
                </article>
            </section>

            <!--
                Daftar hal yang perlu dikerjakan. Ini yang membuat dasbor
                berguna: angka saja memberi tahu keadaan, tetapi tidak memberi
                tahu apa langkah berikutnya.
            -->
            <section v-if="data.tindakan.length" class="mt-6" aria-label="Perlu tindakan">
                <h2 v-muncul:kiri class="font-display text-xl font-semibold">Perlu tindakan</h2>

                <ul class="mt-3 space-y-3">
                    <li
                        v-for="(butir, urutan) in data.tindakan"
                        :key="butir.judul"
                        v-muncul="{ nama: 'kiri', jeda: urutan * 70 }"
                    >
                        <RouterLink
                            :to="{ name: butir.rute }"
                            class="group flex items-center gap-4 rounded-xl border border-[color:var(--color-garis)] bg-white p-4 transition hover:border-[color:var(--color-indigo)]"
                        >
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[color:var(--color-emas-muda)] text-[color:var(--color-emas)]"
                                aria-hidden="true"
                            >
                                <i class="pi pi-exclamation-circle" />
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold">{{ butir.judul }}</span>
                                <span class="block text-sm text-[color:var(--color-tinta)]/65">
                                    {{ butir.keterangan }}
                                </span>
                            </span>

                            <i
                                class="pi pi-arrow-right shrink-0 text-xs text-[color:var(--color-indigo)] transition-transform group-hover:translate-x-0.5"
                                aria-hidden="true"
                            />
                        </RouterLink>
                    </li>
                </ul>
            </section>

            <p
                v-else
                v-muncul:mekar
                class="mt-6 rounded-xl border border-[color:var(--color-garis)] bg-white p-8 text-center text-sm text-[color:var(--color-tinta)]/60"
            >
                Tidak ada yang perlu dikerjakan sekarang.
            </p>

            <!--
                Antrean verifikasi, hanya untuk admin. Mockup menampilkannya
                sebagai tabel di samping kartu statistik; di sini ia turun ke
                bawah supaya tetap terbaca di layar sempit.
            -->
            <section v-if="data.antrean?.length" class="mt-6" aria-label="Antrean verifikasi">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 v-muncul:kiri class="font-display text-xl font-semibold">
                        Antrean verifikasi pemilik kos
                    </h2>
                    <RouterLink
                        :to="{ name: 'admin-pengajuan' }"
                        class="text-sm font-semibold text-[color:var(--color-indigo)] hover:underline"
                    >
                        Lihat semua
                    </RouterLink>
                </div>

                <div
                    v-muncul="{ nama: 'naik', jeda: 90 }"
                    class="mt-3 overflow-x-auto rounded-xl border border-[color:var(--color-garis)] bg-white"
                >
                    <table class="w-full min-w-[34rem] text-sm">
                        <thead class="border-b border-[color:var(--color-garis)] text-left text-xs tracking-wide text-[color:var(--color-tinta)]/55 uppercase">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-semibold">Pemohon</th>
                                <th scope="col" class="px-4 py-3 font-semibold">Dokumen</th>
                                <th scope="col" class="px-4 py-3 font-semibold">Diajukan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="pengajuan in data.antrean"
                                :key="pengajuan.id"
                                class="border-b border-[color:var(--color-garis)] last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ pengajuan.nama_lengkap }}</p>
                                    <p class="text-xs text-[color:var(--color-tinta)]/55">
                                        akun {{ pengajuan.pemohon }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded bg-[color:var(--color-kertas)] px-2 py-1 text-xs font-medium">KTP</span>
                                    <span
                                        v-if="pengajuan.punya_dokumen"
                                        class="ml-1 rounded bg-[color:var(--color-kertas)] px-2 py-1 text-xs font-medium"
                                    >
                                        Kepemilikan
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-[color:var(--color-tinta)]/70">
                                    {{ waktu(pengajuan.diajukan_pada) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </template>
    </div>
</template>
