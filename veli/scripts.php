<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Tab Geçiş Fonksiyonu
    function openTab(id) {
        document.querySelectorAll('.tab-content').forEach(e => {
            e.classList.add('hidden');
            e.classList.remove('block');
        });
        const content = document.getElementById('content-'+id);
        if(content) {
            content.classList.remove('hidden');
            content.classList.add('block');
        }
        
        const buttons = ['duyuru', 'schedule', 'rapor', 'odeme'];
        buttons.forEach(btnId => {
            document.getElementById('tab-' + btnId).className = "whitespace-nowrap px-5 py-2 rounded-xl font-bold text-xs transition text-slate-500 hover:bg-slate-50 flex items-center gap-2";
        });
        document.getElementById('tab-'+id).className = "whitespace-nowrap px-5 py-2 rounded-xl font-bold text-xs transition bg-slate-800 text-white shadow-md flex items-center gap-2";
    }

    // GRAFİK ANALİZİ (Çift Grafik Yapısı)
    document.addEventListener("DOMContentLoaded", function() {
        
        // PHP Verilerini Al
        const totalQ = <?php echo $report_stats['total_q'] ?? 0; ?>;
        const totalT = <?php echo $report_stats['total_t'] ?? 0; ?>;
        const weekQ  = <?php echo $report_stats['week_q'] ?? 0; ?>;
        const weekT  = <?php echo $report_stats['week_t'] ?? 0; ?>;

        // Mantıksal Hesaplama: Sadece "Önceki" kısmı bulalım
        // (Toplam - Bu Hafta = Önceki Birikim)
        const prevQ = Math.max(0, totalQ - weekQ);
        const prevT = Math.max(0, totalT - weekT);

        // Ortak Grafik Ayarları (Analist Stili)
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    padding: 12,
                    cornerRadius: 8,
                    titleFont: { family: "'Poppins', sans-serif", size: 13 },
                    bodyFont: { family: "'Poppins', sans-serif", size: 14, weight: 'bold' },
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y + ' Adet';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9', borderDash: [5, 5] },
                    ticks: { font: { family: "'Poppins', sans-serif", size: 11 } }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { family: "'Poppins', sans-serif", weight: 'bold' } }
                }
            }
        };

        // 1. GRAFİK: SORU HACMİ (Question Volume)
        const ctxQ = document.getElementById('qChart');
        if (ctxQ) {
            new Chart(ctxQ, {
                type: 'bar',
                data: {
                    labels: ['Önceki Birikim', 'Bu Hafta', 'Genel Toplam'],
                    datasets: [{
                        label: 'Soru',
                        data: [prevQ, weekQ, totalQ],
                        backgroundColor: [
                            'rgba(203, 213, 225, 0.5)', // Gri (Önceki)
                            'rgba(79, 70, 229, 0.8)',   // Indigo (Bu Hafta)
                            'rgba(30, 41, 59, 0.9)'     // Koyu (Toplam)
                        ],
                        borderColor: [
                            'rgb(203, 213, 225)',
                            'rgb(79, 70, 229)',
                            'rgb(30, 41, 59)'
                        ],
                        borderWidth: 2,
                        borderRadius: 8,
                        barPercentage: 0.6
                    }]
                },
                options: commonOptions
            });
        }

        // 2. GRAFİK: KONU İLERLEMESİ (Topic Progress)
        const ctxT = document.getElementById('tChart');
        if (ctxT) {
            new Chart(ctxT, {
                type: 'bar',
                data: {
                    labels: ['Önceki Konular', 'Bu Hafta', 'Genel Toplam'],
                    datasets: [{
                        label: 'Konu',
                        data: [prevT, weekT, totalT],
                        backgroundColor: [
                            'rgba(203, 213, 225, 0.5)', // Gri
                            'rgba(234, 179, 8, 0.8)',   // Sarı (Bu Hafta)
                            'rgba(161, 98, 7, 0.9)'     // Koyu Sarı
                        ],
                        borderColor: [
                            'rgb(203, 213, 225)',
                            'rgb(234, 179, 8)',
                            'rgb(161, 98, 7)'
                        ],
                        borderWidth: 2,
                        borderRadius: 8,
                        barPercentage: 0.6
                    }]
                },
                options: commonOptions
            });
        }
    });
</script>