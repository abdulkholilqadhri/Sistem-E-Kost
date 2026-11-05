/**
 * Susunan menu sidebar untuk tiap peran.
 *
 * Mengikuti SDD BAB IV, mockup Halaman Pembayaran (hal. 61) untuk penyewa dan
 * Halaman Dasbor Admin (hal. 62) untuk admin. Menu pemilik kos tidak
 * digambarkan di dokumen, jadi disusun dengan pola yang sama.
 *
 * DUA PENYIMPANGAN YANG DISENGAJA DARI MOCKUP
 *
 * 1. "Jadi Pemilik" ditambahkan ke menu penyewa. Mockup tidak memuatnya,
 *    tetapi FR-14 mewajibkan jalur pengajuan dan halaman itu harus punya
 *    pintu masuk dari suatu tempat.
 * 2. "Laporan" dan "Pengaturan" pada menu admin dihilangkan. Keduanya ada di
 *    mockup tetapi tidak punya nomor FR, sehingga tidak ada halaman yang
 *    boleh dibuka olehnya. Menu yang menuju halaman kosong lebih buruk
 *    daripada menu yang tidak ada.
 *
 * Menyembunyikan menu bukan pengamanan. Siapa pun bisa mengetik alamatnya
 * langsung; yang menolak adalah middleware role dan Policy di server.
 */
const menuPenyewa = [
    { nama: 'dashboard', label: 'Dashboard', ikon: 'pi-th-large' },
    { nama: 'beranda', label: 'Cari Kos', ikon: 'pi-search' },
    { nama: 'pesanan-saya', label: 'Pemesanan Saya', ikon: 'pi-inbox' },
    { nama: 'pembayaran', label: 'Pembayaran', ikon: 'pi-credit-card' },
    { nama: 'ulasan-saya', label: 'Ulasan', ikon: 'pi-star' },
    { nama: 'jadi-pemilik', label: 'Jadi Pemilik', ikon: 'pi-home' },
    { nama: 'profil', label: 'Profil', ikon: 'pi-user' },
];

const menuPemilik = [
    { nama: 'dashboard', label: 'Dashboard', ikon: 'pi-th-large' },
    { nama: 'pemilik-kos', label: 'Kos Saya', ikon: 'pi-building' },
    { nama: 'pemilik-pesanan', label: 'Pesanan Masuk', ikon: 'pi-inbox' },
    { nama: 'beranda', label: 'Peta Kos', ikon: 'pi-map' },
    { nama: 'profil', label: 'Profil', ikon: 'pi-user' },
];

const menuAdmin = [
    { nama: 'dashboard', label: 'Dashboard', ikon: 'pi-th-large' },
    { nama: 'admin-pengguna', label: 'Pengguna', ikon: 'pi-users' },
    { nama: 'admin-pengajuan', label: 'Verifikasi', ikon: 'pi-verified' },
    { nama: 'beranda', label: 'Data Kos', ikon: 'pi-building' },
    { nama: 'profil', label: 'Profil', ikon: 'pi-user' },
];

export function menuUntuk(peran) {
    return {
        admin: menuAdmin,
        pemilik: menuPemilik,
        penyewa: menuPenyewa,
    }[peran] ?? menuPenyewa;
}

/**
 * Nama area untuk remah-remah di bilah atas, sesuai mockup yang menuliskan
 * "Penyewa › Pembayaran".
 */
export function labelArea(peran) {
    return {
        admin: 'Admin',
        pemilik: 'Pemilik Kos',
        penyewa: 'Penyewa',
    }[peran] ?? 'Pengguna';
}
