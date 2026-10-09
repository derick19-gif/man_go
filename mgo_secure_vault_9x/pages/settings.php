<?php
// pages/settings.php
$stmtSettings = $db->query("SELECT * FROM system_settings");
$settingsData = $stmtSettings->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="mb-8">
    <h2 class="text-3xl font-black text-white">Configurations du Système</h2>
    <p class="text-slate-400 text-sm mt-1">Ajustez les prix des abonnements, les frais et les taux de change dynamiquement.</p>
</div>

<div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-2xl p-6 sm:p-10">
    <form method="POST">
        <input type="hidden" name="action" value="update_settings">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <?php foreach($settingsData as $setting): ?>
                <div>
                    <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-3">
                        <?= htmlspecialchars($setting['setting_name']) ?>
                    </label>
                    <input type="text" name="settings[<?= htmlspecialchars($setting['setting_key']) ?>]" value="<?= htmlspecialchars($setting['setting_value']) ?>" class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl py-4 px-5 font-black outline-none focus:border-purple-500 transition shadow-inner">
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-10 border-t border-slate-800 pt-8 flex justify-end">
            <button type="submit" class="bg-purple-600 hover:bg-purple-500 text-white font-black px-8 py-4 rounded-xl transition flex items-center gap-3 shadow-[0_0_20px_rgba(147,51,234,0.3)]">
                <i class="fa-solid fa-floppy-disk"></i> Enregistrer les modifications
            </button>
        </div>
    </form>
</div>