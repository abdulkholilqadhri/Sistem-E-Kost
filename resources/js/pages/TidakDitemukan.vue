<template>
    <div class="mx-auto max-w-md px-4 py-20 text-center">
        <p class="text-5xl font-semibold tracking-tight text-stone-300">404</p>
        <h1 class="mt-3 text-xl font-semibold">Halaman tidak ditemukan</h1>
        <p class="mt-2 text-sm text-stone-600">
            Alamat yang Anda buka tidak ada atau sudah dipindahkan.
        </p>
        <RouterLink
            :to="{ name: 'beranda' }"
            class="mt-6 inline-block rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700"
        >
            Kembali ke beranda
        </RouterLink>
    </div>
</template>
