<div id="content-odeme" class="tab-content hidden animate-fadeIn">
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 overflow-hidden">
        <h3 class="font-bold text-slate-800 mb-6 flex items-center gap-2">
            <span class="text-xl">💳</span> Ödeme & Seans Geçmişi
        </h3>
        
        <div class="overflow-x-auto rounded-2xl border border-slate-100">
            <table class="w-full text-sm text-left border-collapse">
                <thead class="bg-slate-50 text-slate-500 font-bold uppercase text-[10px] tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="p-4">Seans / Açıklama</th>
                        <th class="p-4">Vade Tarihi</th>
                        <th class="p-4">Tutar</th>
                        <th class="p-4 text-center">Durum</th>
                        <th class="p-4 text-right">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php 
                    if (!empty($payments)):
                        foreach ($payments as $pay): 
                            // 1. Durum Analizi
                            $is_overdue = ($pay['status'] != 'odendi' && strtotime($pay['due_date']) < time());
                            $row_class = 'bg-white';
                            
                            if($pay['status'] == 'odendi') $row_class = 'bg-green-50/30 hover:bg-green-50';
                            elseif($is_overdue) $row_class = 'bg-red-50/30 hover:bg-red-50';
                            else $row_class = 'hover:bg-yellow-50/30';

                            // 2. GELİŞMİŞ TELEFON EŞLEŞTİRME (GARANTİ YÖNTEM)
                            $teacher_phone = $pay['teacher_phone'] ?? '';
                            
                            // Eğer ana sorgudan telefon gelmediyse, doğrudan o öğretmenin ID'sine gidip soralım
                            if (empty($teacher_phone) && !empty($pay['teacher_id'])) {
                                try {
                                    $stmt_check = $pdo->prepare("SELECT phone FROM users WHERE id = ? LIMIT 1");
                                    $stmt_check->execute([$pay['teacher_id']]);
                                    $direct_phone = $stmt_check->fetchColumn();
                                    if ($direct_phone) {
                                        $teacher_phone = $direct_phone;
                                    }
                                } catch(Exception $e) { /* Hata olursa sessiz kal */ }
                            }

                            // 3. Telefon Formatlama ve Link Oluşturma
                            $clean_phone = preg_replace('/[^0-9]/', '', $teacher_phone);
                            
                            $wa_link = "#";
                            $wa_target = "_self";
                            // Hata durumunda çıkacak uyarı mesajı
                            $wa_onclick = "alert('Sistem Hatası: Öğretmen (ID: " . ($pay['teacher_id'] ?? 'Bilinmiyor') . ") iletişim bilgisine ulaşılamadı. Lütfen kurumla iletişime geçiniz.'); return false;";
                            $btn_class = "bg-gray-300 text-gray-500 cursor-not-allowed"; // Pasif buton stili

                            // Numara geçerli mi? (En az 10 hane olmalı)
                            if (strlen($clean_phone) >= 10) {
                                // Başında 0 varsa kaldır
                                if (substr($clean_phone, 0, 1) === '0') $clean_phone = substr($clean_phone, 1);
                                // Başında 90 yoksa ekle
                                if (substr($clean_phone, 0, 2) !== '90') $clean_phone = '90' . $clean_phone;
                                
                                $amount_fmt = number_format($pay['amount'], 2, ',', '.') . ' ₺';
                                $msg_text = urlencode("Merhaba hocam, {$pay['description']} (" . date('d.m.Y', strtotime($pay['due_date'])) . ") için {$amount_fmt} tutarındaki ödeme yapılmıştır. Bilginize.");
                                
                                $wa_link = "https://wa.me/{$clean_phone}?text={$msg_text}";
                                $wa_target = "_blank";
                                $wa_onclick = ""; // Hata yok, tıklanabilir
                                $btn_class = "bg-green-500 hover:bg-green-600 text-white shadow-sm hover:shadow-md"; // Aktif buton stili
                            }
                    ?>
                    <tr class="<?php echo $row_class; ?> transition duration-150 group">
                        <td class="p-4 font-bold text-slate-700">
                            <?php echo htmlspecialchars($pay['description']); ?>
                        </td>

                        <td class="p-4 text-slate-500 font-mono text-xs">
                            <span class="<?php echo $is_overdue ? 'text-red-600 font-bold' : ''; ?>">
                                <?php echo date('d.m.Y', strtotime($pay['due_date'])); ?>
                            </span>
                        </td>

                        <td class="p-4 font-black text-slate-800 tracking-tight">
                            <?php echo number_format($pay['amount'], 2, ',', '.'); ?> ₺
                        </td>

                        <td class="p-4 text-center">
                            <?php if($pay['status'] == 'odendi'): ?>
                                <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 px-3 py-1 rounded-full text-[10px] font-bold border border-green-200">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    Ödendi
                                </span>
                            <?php elseif($is_overdue): ?>
                                <span class="inline-flex items-center gap-1 bg-red-100 text-red-700 px-3 py-1 rounded-full text-[10px] font-bold border border-red-200">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Gecikmiş
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-[10px] font-bold border border-yellow-200">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Bekliyor
                                </span>
                            <?php endif; ?>
                        </td>

                        <td class="p-4 text-right">
                            <?php if($pay['status'] != 'odendi'): ?>
                                <a href="<?php echo $wa_link; ?>" 
                                   target="<?php echo $wa_target; ?>" 
                                   onclick="<?php echo $wa_onclick; ?>"
                                   class="inline-flex items-center gap-1.5 <?php echo $btn_class; ?> px-3 py-1.5 rounded-lg text-xs font-medium transition">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.637 3.891 1.685 5.453l-1.112 4.062 3.917-1.214z"/></svg>
                                    Dekont Gönder
                                </a>
                            <?php else: ?>
                                <span class="text-xs text-green-600 font-medium flex items-center justify-end gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Tamamlandı
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php else: ?>
                        <tr><td colspan="5" class="p-12 text-center text-slate-400 font-medium flex-col items-center">
                            <div class="text-3xl mb-2">📂</div>
                            Henüz kayıtlı bir ödeme planı bulunmamaktadır.
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>