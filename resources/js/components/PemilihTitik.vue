<script setup>
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import L from 'leaflet';
import Button from 'primevue/button';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import ikonPenanda from 'leaflet/dist/images/marker-icon.png';
import ikonPenanda2x from 'leaflet/dist/images/marker-icon-2x.png';
import bayanganPenanda from 'leaflet/dist/images/marker-shadow.png';
import api from '../api';

L.Icon.Default.mergeOptions({
    iconRetinaUrl: ikonPenanda2x,
    iconUrl: ikonPenanda,
    shadowUrl: bayanganPenanda,
});

/**
 * Peta untuk menentukan titik lokasi kos.
 *
 * Koordinat tidak pernah diketik manual. Mengetik lintang dan bujur adalah
 * sumber kesalahan yang mudah terjadi — satu angka tertukar membuat kos
 * muncul di laut, dan tidak ada yang menyadarinya sampai ada yang mencari.
 *
 * Pencarian alamat disediakan karena tanpa itu pemilik harus menggeser peta
 * dari titik kampus sampai menemukan lokasinya sendiri.
 */
const props = defineProps({
    lat: { type: [Number, String], default: null },
    lng: { type: [Number, String], default: null },
});

const emit = defineEmits(['update:lat', 'update:lng']);

const konfigurasi = window.EKOST ?? {};
const wadah = ref(null);
const peta = shallowRef(null);
const penanda = shallowRef(null);

const kata = ref('');
const hasil = ref([]);
const mencari = ref(false);
const pesanCari = ref(null);

function titikAwal() {
    if (props.lat != null && props.lng != null) {
        return [Number(props.lat), Number(props.lng)];
    }

    if (konfigurasi.kampus?.lat != null) {
        return [konfigurasi.kampus.lat, konfigurasi.kampus.lng];
    }

    return [-3.537523, 118.990636];
}

function tempatkan([lat, lng], pindahkanPeta = false) {
    if (penanda.value) {
        penanda.value.setLatLng([lat, lng]);
    } else {
        penanda.value = L.marker([lat, lng], { draggable: true }).addTo(peta.value);

        penanda.value.on('dragend', () => {
            const posisi = penanda.value.getLatLng();
            emit('update:lat', Number(posisi.lat.toFixed(8)));
            emit('update:lng', Number(posisi.lng.toFixed(8)));
        });
    }

    if (pindahkanPeta) {
        peta.value.setView([lat, lng], 17);
    }

    // Dibulatkan ke 8 angka di belakang koma agar cocok dengan DECIMAL(10,8)
    // dan DECIMAL(11,8) di basis data. Tanpa pembulatan, MySQL memotongnya
    // sendiri dan nilai tersimpan berbeda dari yang tampil di layar.
    emit('update:lat', Number(lat.toFixed(8)));
    emit('update:lng', Number(lng.toFixed(8)));
}

async function cariAlamat() {
    if (kata.value.trim().length < 3) {
        pesanCari.value = 'Ketik minimal tiga huruf.';

        return;
    }

    mencari.value = true;
    pesanCari.value = null;

    try {
        const { data } = await api.get('/geocode', { params: { q: kata.value } });
        hasil.value = data.data;

        if (hasil.value.length === 0) {
            pesanCari.value = 'Alamat tidak ditemukan. Coba kata lain, atau tandai langsung di peta.';
        }
    } catch {
        hasil.value = [];
        pesanCari.value = 'Pencarian alamat sedang tidak tersedia. Tandai langsung di peta.';
    } finally {
        mencari.value = false;
    }
}

function pilihHasil(item) {
    tempatkan([item.lat, item.lng], true);
    hasil.value = [];
    kata.value = item.nama;
}

onMounted(() => {
    const awal = titikAwal();

    peta.value = L.map(wadah.value, { center: awal, zoom: 16 });

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">Kontributor OpenStreetMap</a>',
    }).addTo(peta.value);

    if (props.lat != null && props.lng != null) {
        tempatkan(awal);
    }

    peta.value.on('click', (peristiwa) => {
        tempatkan([peristiwa.latlng.lat, peristiwa.latlng.lng]);
    });
});

watch(
    () => [props.lat, props.lng],
    ([lat, lng]) => {
        if (lat != null && lng != null && peta.value && !penanda.value) {
            tempatkan([Number(lat), Number(lng)]);
        }
    },
);

onBeforeUnmount(() => {
    peta.value?.remove();
    peta.value = null;
});
</script>

<template>
    <div>
        <div class="relative mb-2">
            <form class="flex gap-2" @submit.prevent="cariAlamat">
                <IconField class="flex-1">
                    <InputIcon class="pi pi-search" />
                    <InputText
                        v-model="kata"
                        placeholder="Cari alamat, mis. Jalan Baharuddin Lopa Majene"
                        class="w-full"
                        aria-label="Cari alamat kos"
                    />
                </IconField>
                <Button
                    type="submit"
                    label="Cari"
                    severity="secondary"
                    outlined
                    :loading="mencari"
                />
            </form>

            <ul
                v-if="hasil.length"
                class="absolute inset-x-0 top-full z-[500] mt-1 max-h-56 overflow-auto rounded-lg border border-[color:var(--color-garis)] bg-white shadow-lg"
            >
                <li v-for="item in hasil" :key="`${item.lat},${item.lng}`">
                    <button
                        type="button"
                        class="block w-full px-3 py-2 text-left text-sm hover:bg-[color:var(--color-kertas)]"
                        @click="pilihHasil(item)"
                    >
                        {{ item.nama }}
                    </button>
                </li>
            </ul>
        </div>

        <p v-if="pesanCari" class="mb-2 text-sm text-[color:var(--color-maroon)]">
            {{ pesanCari }}
        </p>

        <!--
            Tinggi dipasang pada wadah luar, bukan pada elemen peta.
            Kelas .peta memakai height:100% dan ditulis tanpa @layer di
            app.css, sehingga ia selalu mengalahkan kelas tinggi dari
            Tailwind. Memberi h-72 langsung pada elemen petanya tidak
            berpengaruh apa pun, dan petanya runtuh jadi setinggi nol.
        -->
        <div class="h-72 overflow-hidden rounded-lg border border-[color:var(--color-garis)]">
            <div ref="wadah" class="peta" />
        </div>

        <p class="mt-1.5 text-xs text-[color:var(--color-tinta)]/60">
            Cari alamat di atas, lalu geser penandanya tepat ke bangunan kos.
            Bisa juga langsung klik di peta.
            <template v-if="lat != null && lng != null">
                <br>
                Titik terpilih: {{ Number(lat).toFixed(6) }}, {{ Number(lng).toFixed(6) }}
            </template>
            <template v-else>
                <br>
                <span class="text-[color:var(--color-maroon)]">Belum ada titik terpilih.</span>
            </template>
        </p>
    </div>
</template>
