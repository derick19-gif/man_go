<footer class="mango-footer" style="background: #090d16; color: #94a3b8; padding: 60px 20px 30px; border-top: 1px solid #1e293b;">
    <div class="container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 40px; margin-bottom: 40px;">
        
        <!-- Bloc 1: Prsentation MAN GO -->
        <div>
            <a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/" class="mango-logo" style="display: inline-block; margin-bottom: 15px; text-decoration: none; font-size: 24px; font-weight: 800; color: #fff;">
                MAN <span style="color: var(--accent-green, #10b981);">GO</span>
            </a>
            <p style="font-size: 14px; line-height: 1.6; color: #64748b; margin-top: 5px;">
                La marketplace universelle multi-vendeurs : achetez, vendez des produits, dcouvrez des boutiques et accdez  des services de proximit.
            </p>
        </div>

        <!-- Bloc 2: Navigation Marketplace -->
        <div>
            <h4 style="color: #fff; font-size: 16px; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 0.5px;">Navigation</h4>
            <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; font-size: 14px;">
                <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/product" style="color: #94a3b8; text-decoration: none; transition: color 0.2s;">Toutes les Annonces</a></li>
                <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/stands" style="color: #94a3b8; text-decoration: none; transition: color 0.2s;">Boutiques & Stands</a></li>
                <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/services" style="color: #94a3b8; text-decoration: none; transition: color 0.2s;">Prestataires de Services</a></li>
                <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/register" style="color: #94a3b8; text-decoration: none; transition: color 0.2s;">Devenir Vendeur</a></li>
            </ul>
        </div>

        <!-- Bloc 3: Opportunits & Programme (Remplaant de la Logistique) -->
        <div>
            <h4 style="color: #fff; font-size: 16px; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 0.5px;">Programmes & Vendeurs</h4>
            <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; font-size: 14px;">
                <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/plans" style="color: #94a3b8; text-decoration: none; transition: color 0.2s;">Forfaits & Abonnements</a></li>
                <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/referral" style="color: #94a3b8; text-decoration: none; transition: color 0.2s;">Programme de Parrainage</a></li>
                <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/verification" style="color: #94a3b8; text-decoration: none; transition: color 0.2s;">Certification KYC Vendeur</a></li>
                <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/faq" style="color: #94a3b8; text-decoration: none; transition: color 0.2s;">Centre d'aide & FAQ</a></li>
            </ul>
        </div>

        <!-- Bloc 4: Assistance & Contact -->
        <div>
            <h4 style="color: #fff; font-size: 16px; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 0.5px;">Assistance</h4>
            <p style="font-size: 14px; color: #94a3b8; margin-bottom: 12px;">Support Vendeurs & Acheteurs :</p>
            <a href="mailto:support@mango-app.com" style="color: var(--accent-green, #10b981); font-weight: 600; text-decoration: none; font-size: 14px; word-break: break-all;">
                support@mango-app.com
            </a>
            <div style="margin-top: 20px; display: flex; gap: 12px;">
                <a href="#" style="color: #94a3b8; font-size: 18px; width: 36px; height: 36px; background: #1e293b; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#" style="color: #94a3b8; font-size: 18px; width: 36px; height: 36px; background: #1e293b; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;"><i class="fa-brands fa-whatsapp"></i></a>
                <a href="#" style="color: #94a3b8; font-size: 18px; width: 36px; height: 36px; background: #1e293b; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;"><i class="fa-brands fa-instagram"></i></a>
            </div>
        </div>

    </div>

    <!-- Mentions Lgales & Copyright -->
    <div style="border-top: 1px solid #1e293b; padding-top: 25px; text-align: center; font-size: 13px; color: #64748b;">
        <p>&copy; <?= date('Y'); ?> MAN GO - Marketplace Universelle. Tous droits rservs.</p>
    </div>
</footer>

</body>
</html>