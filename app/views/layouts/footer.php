<footer class="mango-footer" style="background: #090d16; color: #94a3b8; padding: 60px 20px 30px; border-top: 1px solid #1e293b;">
    <div class="container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 40px; margin-bottom: 40px; max-width: 1280px; margin-left: auto; margin-right: auto;">
        
        <!-- Bloc 1: Présentation MAN GO -->
        <div>
            <a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/" class="mango-logo" style="display: inline-block; margin-bottom: 15px; text-decoration: none; font-size: 24px; font-weight: 800; color: #fff;">
                MAN <span style="color: var(--accent-green, #f59e0b);">GO</span>
            </a>
            <p style="font-size: 14px; line-height: 1.6; color: #64748b; margin-top: 5px;">
                La marketplace universelle multi-vendeurs : achetez, vendez des produits, découvrez des boutiques et accédez à des services de proximité.
            </p>
        </div>

        <!-- Bloc 2: Navigation Marketplace -->
        <div>
            <h4 style="color: #fff; font-size: 16px; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 0.5px;">Navigation</h4>
            <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; font-size: 14px;">
                <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/listings.php" style="color: #94a3b8; text-decoration: none; transition: color 0.2s;">Toutes les Annonces</a></li>
                <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/stands" style="color: #94a3b8; text-decoration: none; transition: color 0.2s;">Boutiques & Stands</a></li>
                <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/services" style="color: #94a3b8; text-decoration: none; transition: color 0.2s;">Prestataires de Services</a></li>
                <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/register.php" style="color: #94a3b8; text-decoration: none; transition: color 0.2s;">Devenir Vendeur</a></li>
                <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/terms.php" style="color: #94a3b8; text-decoration: none; transition: color 0.2s;">Conditions d'utilisation</a></li>
            </ul>
        </div>

        <!-- Bloc 3: Opportunités & Programme (Intelligent) -->
        <div>
            <h4 style="color: #fff; font-size: 16px; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 0.5px;">Programmes</h4>
            <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; font-size: 14px;">
                <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/referral.php" style="color: #94a3b8; text-decoration: none; transition: color 0.2s;">Programme de Parrainage</a></li>
                
                <?php 
                // N'affiche le KYC et les abonnements QUE si ce n'est PAS un simple acheteur
                $currentRole = $_SESSION['user_role'] ?? 'buyer';
                if ($currentRole !== 'buyer' && $currentRole !== 'client'): 
                ?>
                    <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/plans" style="color: #94a3b8; text-decoration: none; transition: color 0.2s;">Forfaits & Abonnements</a></li>
                    <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/verification.php" style="color: #94a3b8; text-decoration: none; transition: color 0.2s;">Certification KYC Vendeur</a></li>
                <?php endif; ?>
                
                <li><a href="<?= defined('APP_URL') ? APP_URL : '/man_go'; ?>/faq.php" style="color: #94a3b8; text-decoration: none; transition: color 0.2s;">Centre d'aide & FAQ</a></li>
            </ul>
        </div>

        <!-- Bloc 4: Assistance & Contact -->
        <div>
            <h4 style="color: #fff; font-size: 16px; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 0.5px;">Assistance</h4>
            <p style="font-size: 14px; color: #94a3b8; margin-bottom: 12px;">Support Vendeurs & Acheteurs :</p>
            <a href="mailto:support@mango-app.com" style="color: var(--accent-green, #f59e0b); font-weight: 600; text-decoration: none; font-size: 14px; word-break: break-all;">
                support@mango-app.com
            </a>
            <div style="margin-top: 20px; display: flex; gap: 12px;">
                <a href="#" style="color: #94a3b8; font-size: 18px; width: 36px; height: 36px; background: #1e293b; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#" style="color: #94a3b8; font-size: 18px; width: 36px; height: 36px; background: #1e293b; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;"><i class="fa-brands fa-whatsapp"></i></a>
                <a href="#" style="color: #94a3b8; font-size: 18px; width: 36px; height: 36px; background: #1e293b; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;"><i class="fa-brands fa-instagram"></i></a>
            </div>
        </div>

    </div>

    <!-- Mentions Légales & Copyright -->
    <div style="border-top: 1px solid #1e293b; padding-top: 25px; text-align: center; font-size: 13px; color: #64748b;">
        <p>&copy; <?= date('Y'); ?> MAN GO - Marketplace Universelle. Tous droits réservés.</p>
    </div>
</footer>

<!-- SCRIPT GLOBAL MAN GO : Activer le défilement horizontal (Swipe/Drag) -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const sliders = document.querySelectorAll('.overflow-x-auto, .flex-nowrap');
    
    let isDown = false;
    let startX;
    let scrollLeft;

    sliders.forEach(slider => {
        slider.classList.add('hide-scrollbar');
        slider.style.cursor = 'grab';

        // LE SECRET EST ICI : Empêcher les liens et images de créer un "fantôme"
        slider.querySelectorAll('a, img').forEach(el => {
            el.addEventListener('dragstart', (e) => e.preventDefault());
        });

        slider.addEventListener('mousedown', (e) => {
            isDown = true;
            slider.style.cursor = 'grabbing';
            startX = e.pageX - slider.offsetLeft;
            scrollLeft = slider.scrollLeft;
        });

        slider.addEventListener('mouseleave', () => {
            isDown = false;
            slider.style.cursor = 'grab';
        });

        slider.addEventListener('mouseup', () => {
            isDown = false;
            slider.style.cursor = 'grab';
        });

        slider.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - slider.offsetLeft;
            const walk = (x - startX) * 2; 
            slider.scrollLeft = scrollLeft - walk;
        });
        
        slider.addEventListener('touchstart', (e) => {
            startX = e.touches[0].pageX - slider.offsetLeft;
            scrollLeft = slider.scrollLeft;
        }, {passive: true});

        slider.addEventListener('touchmove', (e) => {
            const x = e.touches[0].pageX - slider.offsetLeft;
            const walk = (x - startX) * 2;
            slider.scrollLeft = scrollLeft - walk;
        }, {passive: true});
    });
});
</script>
</body>
</html>