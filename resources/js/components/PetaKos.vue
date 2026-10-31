<script setup>
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import L from 'leaflet';

const props = defineProps({
    daftarKos: { type: Array, default: () => [] },
    acuan: { type: Object, default: null },
    radius: { type: Number, default: 1000 },
    rute: { type: Object, default: null },
    kosTersorot: { type: [Number, String], default: null },
});

const emit = defineEmits(['pilih-kos', 'geser-acuan']);

const wadah = ref(null);

// shallowRef, bukan ref: objek Leaflet punya rujukan melingkar ke DOM, dan
// membuatnya reaktif secara mendalam akan membuat Vue menelusurinya tanpa
// henti sampai aplikasi tersendat.
const peta = shallowRef(null);
const lapisanPenanda = shallowRef(null);
const lapisanRute = shallowRef(null);
const penandaAcuan = shallowRef(null);
const lingkaranRadius = shallowRef(null);
const penandaPerKos = shallowRef(new Map());

let pengamatUkuran = null;
let kunciHasilTerakhir = '';

/**
 * Harga ditulis singkat supaya muat di penanda peta: "700 rb", "1,2 jt".
 */
function hargaSingkat(angka) {
    if (!angka) {
        return '-';
    }

    if (angka >= 1_000_000) {
        return `${(angka / 1_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} jt`;
    }

    return `${Math.round(angka / 1000)} rb`;
}

function lolosHtml(teks) {
    const elemen = document.createElement('div');
    elemen.textContent = teks ?? '';

    return elemen.innerHTML;
}

/**
 * Penanda berbentuk label harga, bukan pin bawaan Leaflet.
 *
 * Pin seragam memaksa pengguna mengklik satu per satu hanya untuk tahu
 * harganya. Dengan label, perbandingan harga antarlokasi terbaca langsung
 * dari peta — dan itu salah satu dari dua pertanyaan utama pencari kos,
 * bersama jarak.
 */
function ikonHarga(kos, aktif) {
    return L.divIcon({
        className: 'penanda-wadah',
        html: `<span class="penanda-harga${aktif ? ' penanda-harga--aktif' : ''}">${lolosHtml(hargaSingkat(kos.harga_per_bulan))}</span>`,
        iconSize: [0, 0],
    });
}

function buatPeta() {
    const pusat = props.acuan ?? { lat: -3.537523, lng: 118.990636 };

    peta.value = L.map(wadah.value, {
        center: [pusat.lat, pusat.lng],
        zoom: 15,
        scrollWheelZoom: true,
        zoomControl: true,
    });

    // Atribusi OpenStreetMap wajib ditampilkan — ini syarat lisensi ODbL,
    // bukan sekadar sopan santun.
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">Kontributor OpenStreetMap</a>',
    }).addTo(peta.value);

    lapisanPenanda.value = L.layerGroup().addTo(peta.value);
    lapisanRute.value = L.layerGroup().addTo(peta.value);

    peta.value.on('click', (peristiwa) => {
        emit('geser-acuan', { lat: peristiwa.latlng.lat, lng: peristiwa.latlng.lng });
    });

    /*
     * Leaflet menghitung ukuran petanya sekali saat dibuat. Bila wadahnya
     * sedang tersembunyi — misalnya saat tampilan ponsel masih menampilkan
     * daftar — ukuran yang tercatat adalah nol, dan setelah ditampilkan
     * petanya hanya menggambar satu petak abu-abu di pojok.
     *
     * ResizeObserver memberi tahu Leaflet setiap kali ukurannya berubah,
     * sehingga perpindahan tampilan dan perubahan ukuran jendela tertangani
     * tanpa perlu dipanggil manual dari setiap halaman.
     */
    pengamatUkuran = new ResizeObserver(() => peta.value?.invalidateSize());
    pengamatUkuran.observe(wadah.value);

    gambarAcuan();
    gambarPenanda();
}

function gambarAcuan() {
    if (!peta.value || !props.acuan) {
        return;
    }

    const posisi = [props.acuan.lat, props.acuan.lng];

    if (penandaAcuan.value) {
        penandaAcuan.value.setLatLng(posisi);
    } else {
        penandaAcuan.value = L.circleMarker(posisi, {
            radius: 8,
            color: '#16233f',
            fillColor: '#c8922a',
            fillOpacity: 1,
            weight: 3,
        })
            .addTo(peta.value)
            .bindTooltip(props.acuan.nama ?? 'Titik acuan', { direction: 'top' });
    }

    if (props.radius > 0) {
        if (lingkaranRadius.value) {
            lingkaranRadius.value.setLatLng(posisi).setRadius(props.radius);
        } else {
            lingkaranRadius.value = L.circle(posisi, {
                radius: props.radius,
                color: '#16233f',
                weight: 1,
                opacity: 0.35,
                fillOpacity: 0.05,
            }).addTo(peta.value);
        }
    } else if (lingkaranRadius.value) {
        lingkaranRadius.value.remove();
        lingkaranRadius.value = null;
    }
}

function gambarPenanda() {
    if (!lapisanPenanda.value) {
        return;
    }

    lapisanPenanda.value.clearLayers();
    penandaPerKos.value = new Map();

    props.daftarKos.forEach((kos) => {
        const penanda = L.marker([kos.latitude, kos.longitude], {
            icon: ikonHarga(kos, kos.id === props.kosTersorot),
            riseOnHover: true,
        }).addTo(lapisanPenanda.value);

        penanda.bindTooltip(kos.nama_kos, { direction: 'top', offset: [0, -14] });
        penanda.on('click', () => emit('pilih-kos', kos.id));
        penanda.on('mouseover', () => emit('pilih-kos', kos.id));

        penandaPerKos.value.set(kos.id, penanda);
    });

    sesuaikanJangkauan();
}

/**
 * Menyesuaikan tampilan peta agar seluruh hasil pencarian terlihat.
 *
 * Hanya dijalankan saat kumpulan hasilnya benar-benar berubah. Tanpa
 * penjagaan itu, peta akan melompat kembali setiap kali pengguna menggeser
 * atau memperbesar — persis saat mereka sedang memeriksa satu daerah.
 */
function sesuaikanJangkauan() {
    if (!peta.value || props.daftarKos.length === 0) {
        return;
    }

    const kunci = props.daftarKos.map((kos) => kos.id).join(',');

    if (kunci === kunciHasilTerakhir) {
        return;
    }

    kunciHasilTerakhir = kunci;

    const titik = props.daftarKos.map((kos) => [kos.latitude, kos.longitude]);

    if (props.acuan) {
        titik.push([props.acuan.lat, props.acuan.lng]);
    }

    peta.value.fitBounds(L.latLngBounds(titik), {
        padding: [48, 48],
        maxZoom: 17,
    });
}

function gambarRute() {
    if (!lapisanRute.value) {
        return;
    }

    lapisanRute.value.clearLayers();

    if (!props.rute?.geometri?.coordinates) {
        return;
    }

    // GeoJSON memakai urutan [bujur, lintang]; Leaflet memakai [lintang,
    // bujur]. Harus dibalik, atau garisnya muncul di benua yang salah.
    const titik = props.rute.geometri.coordinates.map(([lng, lat]) => [lat, lng]);

    const garis = L.polyline(titik, {
        color: '#1f3766',
        weight: 5,
        opacity: 0.85,
    }).addTo(lapisanRute.value);

    peta.value?.fitBounds(garis.getBounds(), { padding: [40, 40] });
}

watch(() => props.daftarKos, gambarPenanda, { deep: false });
watch(() => [props.acuan, props.radius], gambarAcuan, { deep: true });
watch(() => props.rute, gambarRute, { deep: false });

/*
 * Penanda yang sedang disorot hanya diganti ikonnya, bukan digambar ulang
 * seluruhnya. Menggambar ulang semua penanda setiap kali kursor berpindah
 * kartu akan membuat peta berkedip.
 */
watch(
    () => props.kosTersorot,
    (baru, lama) => {
        [lama, baru].forEach((id) => {
            const penanda = penandaPerKos.value.get(id);
            const kos = props.daftarKos.find((item) => item.id === id);

            if (penanda && kos) {
                penanda.setIcon(ikonHarga(kos, id === baru));
            }
        });
    },
);

onMounted(buatPeta);

onBeforeUnmount(() => {
    pengamatUkuran?.disconnect();
    pengamatUkuran = null;

    // Tanpa remove(), Leaflet meninggalkan pendengar peristiwa pada window
    // yang terus hidup setelah komponen hilang — kebocoran memori yang baru
    // terasa setelah pengguna berpindah halaman berkali-kali.
    peta.value?.remove();
    peta.value = null;
});
</script>

<template>
    <div ref="wadah" class="peta" />
</template>
