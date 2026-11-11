/**
 * Ikon primeicons untuk tiap fasilitas.
 *
 * Daftar fasilitasnya sendiri tidak ditulis di sini. Sumbernya tetap
 * config/ekost.php; modul ini hanya memberi gambar pada nama yang sudah ada.
 * Kuncinya harus dieja persis sama dengan isi config — penjelasannya ada di
 * sana.
 *
 * Primeicons tidak punya ikon kamar mandi, kasur, atau pendingin ruangan.
 * Untuk fasilitas semacam itu dipilih ikon yang paling dekat maknanya; labelnya
 * selalu tampil di sebelah ikon, jadi ikon tidak pernah harus menjelaskan
 * dirinya sendiri.
 */
const ikonPerFasilitas = {
    'WiFi': 'pi-wifi',
    'Kamar Mandi Dalam': 'pi-home',
    'AC': 'pi-cloud',
    'Kasur': 'pi-moon',
    'Lemari': 'pi-box',
    'Meja Belajar': 'pi-book',
    'Dapur Bersama': 'pi-users',
    'Parkir Motor': 'pi-car',
    'Air Panas': 'pi-sun',
    'Laundry': 'pi-sync',
};

/**
 * Fasilitas yang ditambahkan ke config tanpa ikon tetap tampil dengan tanda
 * centang — tanda yang sama dengan daftar fasilitas di halaman detail kos —
 * bukan menghilang atau memunculkan ruang kosong.
 */
export function ikonFasilitas(nama) {
    return ikonPerFasilitas[nama] ?? 'pi-check';
}
