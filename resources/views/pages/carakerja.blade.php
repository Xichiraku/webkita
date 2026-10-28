@php
    // [judul, deskripsi, ikon, tipe]  tipe: start | process | decision | revision | end
    $steps = [
        ['Mulai Pemesanan', 'Klien memulai proses pemesanan layanan website.', 'play', 'start'],
        ['Akses Website Webkita', 'Klien mengakses website Webkita untuk melakukan pemesanan layanan.', 'globe', 'process'],
        ['Pencatatan Data & Informasi', 'Sistem mencatat data pesanan dan meneruskan informasi kepada tim pengembang.', 'database', 'process'],
        ['Analisis Kebutuhan', 'Tim pengembang menganalisis kebutuhan klien, menyusun rencana proyek, dan estimasi biaya.', 'users', 'process'],
        ['Presentasi Penawaran & Keputusan Klien', 'Tim mempresentasikan penawaran. Jika diterima (YA): Lanjut ke Pembangunan. Jika ditolak (TIDAK): Proses selesai atau negosiasi ulang.', 'circle-check', 'decision'],
        ['Proses Pembangunan Website', 'Tim pengembang melaksanakan proses pembangunan website sesuai dengan kebutuhan dan spesifikasi klien.', 'wrench', 'process'],
        ['Testing Internal (QA)', 'Tim Quality Assurance (QA) melakukan uji coba internal yang ketat terhadap hasil pengembangan.', 'test-tube', 'process'],
        ['Finalisasi Hasil Uji Coba', 'Hasil testing diverifikasi. Jika Lolos (YA): Lanjut ke Serah Terima. Jika Tidak Lolos (TIDAK): Masuk ke Perbaikan/Revisi.', 'square-check', 'decision'],
        ['Perbaikan/Revisi Produk', 'Tim melakukan perbaikan dan revisi berdasarkan temuan uji coba (bug, penyesuaian). Setelah revisi, kembali ke tahap Testing Internal.', 'refresh-cw', 'revision'],
        ['Serah Terima Proyek', 'Tim menyelesaikan finalisasi produk dan melakukan serah terima proyek (penyerahan kode, aset, dokumentasi) kepada klien.', 'file-check', 'process'],
        ['Maintenance & Support', 'Tim menyediakan layanan pemeliharaan (maintenance) dan dukungan (support) pasca-proyek.', 'wrench', 'process'],
        ['Proses Selesai', 'Proses produksi website telah selesai dengan sukses.', 'circle-check', 'end'],
    ];
    $tone = ['start' => 'bg-green-500', 'end' => 'bg-green-500', 'decision' => 'bg-yellow-500', 'revision' => 'bg-orange-500', 'process' => 'bg-gray-900'];
@endphp
<x-layouts.site title="Cara Kerja">
<div class="bg-white">
    <section class="py-16 lg:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="reveal mb-12 text-center lg:mb-16">
                <span class="mb-4 inline-block rounded-lg bg-blue-100 px-4 py-2 text-sm font-semibold text-blue-600">Proses Kerja Kami</span>
                <h1 class="mb-4 text-3xl font-bold text-gray-900 sm:text-4xl lg:text-5xl">Cara Kerja Kami</h1>
                <p class="mx-auto max-w-2xl text-lg leading-relaxed text-gray-600">Langkah demi langkah dalam proses pengembangan website profesional bersama Webkita.</p>
            </div>

            <div class="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($steps as $i => [$title, $desc, $icon, $type])
                    <div class="reveal rounded-xl border border-gray-100 bg-white p-8 shadow-lg transition duration-300 hover:-translate-y-1 hover:shadow-2xl" style="--d: {{ ($i % 3) * 80 }}ms">
                        <div class="mb-4 inline-flex rounded-full p-4 text-white {{ $tone[$type] }}">
                            <x-dynamic-component :component="'lucide-'.$icon" class="h-6 w-6" aria-hidden="true" />
                        </div>
                        <h2 class="mb-3 text-xl font-bold text-gray-900">{{ $i + 1 }}. {{ $title }}</h2>
                        <p class="text-gray-600">{{ $desc }}</p>

                        @if ($type === 'decision')
                            <div class="mt-4 space-y-2 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm font-medium text-gray-700">
                                <p class="flex items-center"><x-lucide-circle-check class="mr-2 inline h-4 w-4 text-green-500" aria-hidden="true" /><span>Ya: Lanjut ke langkah berikutnya</span></p>
                                <p class="flex items-center"><x-lucide-circle-x class="mr-2 inline h-4 w-4 text-red-500" aria-hidden="true" /><span>Tidak: Proses ulang/negosiasi</span></p>
                            </div>
                        @elseif ($type === 'revision')
                            <div class="mt-4 rounded-lg border border-orange-200 bg-orange-50 p-4 text-sm font-semibold">
                                <p class="flex items-center text-orange-600"><x-lucide-refresh-cw class="mr-2 h-4 w-4" aria-hidden="true" />Kembali ke Testing Internal (#7)</p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</div>
</x-layouts.site>
