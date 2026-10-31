import { readonly, ref } from 'vue';

/**
 * Keadaan indikator pemuatan halaman.
 *
 * Disimpan di modul biasa, bukan di store Pinia: ini keadaan antarmuka murni
 * yang tidak berhubungan dengan data aplikasi, dan satu-satunya yang
 * membacanya adalah satu komponen.
 *
 * MENGAPA ADA PENUNDAAN SEBELUM MUNCUL
 *
 * Sebagian besar perpindahan halaman selesai dalam puluhan milidetik, apalagi
 * setelah berkasnya tersimpan di cache peramban. Indikator yang muncul dan
 * langsung hilang untuk perpindahan secepat itu justru terbaca sebagai
 * kedipan yang mengganggu, dan membuat aplikasi terasa lebih lambat daripada
 * sebenarnya.
 *
 * Karena itu indikator baru ditampilkan bila perpindahannya melewati ambang
 * di bawah. Perpindahan yang lebih cepat tidak pernah menampilkannya sama
 * sekali.
 */
const AMBANG_TAMPIL_MS = 180;

const aktif = ref(false);
const kemajuan = ref(0);

let penundaTampil = null;
let pengatur = null;

function hentikanPengatur() {
    clearInterval(pengatur);
    pengatur = null;
}

export function mulaiMuat() {
    clearTimeout(penundaTampil);
    hentikanPengatur();

    penundaTampil = setTimeout(() => {
        aktif.value = true;
        kemajuan.value = 12;

        /*
         * Kemajuan yang ditampilkan adalah perkiraan, bukan ukuran sebenarnya:
         * peramban tidak memberi tahu berapa persen sebuah modul selesai
         * diunduh. Pertambahannya dibuat mengecil dan berhenti di 90% supaya
         * tidak pernah tampak selesai sebelum halamannya benar-benar siap.
         */
        pengatur = setInterval(() => {
            const sisa = 90 - kemajuan.value;

            if (sisa <= 1) {
                hentikanPengatur();

                return;
            }

            kemajuan.value += Math.max(0.5, sisa * 0.12);
        }, 220);
    }, AMBANG_TAMPIL_MS);
}

export function selesaiMuat() {
    clearTimeout(penundaTampil);
    hentikanPengatur();

    if (! aktif.value) {
        // Perpindahannya lebih cepat daripada ambang; indikator tidak pernah
        // muncul, jadi tidak ada yang perlu ditutup.
        kemajuan.value = 0;

        return;
    }

    kemajuan.value = 100;

    // Jeda singkat supaya batangnya sempat terlihat mencapai ujung kanan,
    // bukan hilang begitu saja di tengah jalan.
    setTimeout(() => {
        aktif.value = false;
        kemajuan.value = 0;
    }, 220);
}

export function useIndikatorMuat() {
    return {
        aktif: readonly(aktif),
        kemajuan: readonly(kemajuan),
    };
}
