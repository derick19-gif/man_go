# ==========================================
# 1. BIBLIOTHÈQUES STANDARDS (Python)
# ==========================================
import os
import json
import sqlite3
import threading
import time
import requests
from datetime import datetime, timedelta

# ==========================================
# 2. BIBLIOTHÈQUES TIERCES (Sécurité & Plugins)
# ==========================================
from werkzeug.security import generate_password_hash, check_password_hash

try:
    from plyer import vibrator
except ImportError:
    vibrator = None

# ==========================================
# 3. KIVY (Core & UI)
# ==========================================
from kivy.lang import Builder
from kivy.clock import Clock
from kivymd.uix.card import MDCard
from kivy.animation import Animation
from kivy.utils import platform
from kivy.core.window import Window
from kivy.core.clipboard import Clipboard
from kivy.uix.screenmanager import ScreenManager, Screen
from kivy.properties import (
    StringProperty, 
    ListProperty, 
    BooleanProperty, 
    NumericProperty, 
    ObjectProperty
)

# ==========================================
# 4. KIVYMD (UI Components)
# ==========================================
from kivy.metrics import dp
from kivymd.app import MDApp
from kivymd.uix.screen import MDScreen
from kivymd.uix.boxlayout import MDBoxLayout
from kivymd.uix.floatlayout import MDFloatLayout
from kivymd.uix.relativelayout import MDRelativeLayout
from kivymd.uix.scrollview import MDScrollView
# Remplace ton bloc try/except par ces deux lignes :
from kivy.uix.modalview import ModalView
class MDModalView(ModalView): pass

# Dialogues et Notifications
from kivymd.uix.snackbar import Snackbar
from kivy.utils import get_color_from_hex
from kivymd.uix.dialog import MDDialog
from kivymd.toast import toast
from kivymd.uix.spinner import MDSpinner

# Boutons et Icônes
from kivymd.uix.button import (
    MDIconButton, 
    MDFlatButton, 
    MDRaisedButton, 
    MDFillRoundFlatButton
)
from kivymd.uix.label import MDIcon, MDLabel

# Listes et Contrôles
from kivymd.uix.list import (
    TwoLineAvatarIconListItem, 
    OneLineAvatarIconListItem,
    IRightBodyTouch, 
    IconLeftWidget
)
from kivymd.uix.selectioncontrol import MDCheckbox
from kivymd.uix.textfield import MDTextField
from kivymd.uix.menu import MDDropdownMenu

# Pickers (Dates & Heures)
from kivymd.uix.pickers import MDDatePicker, MDTimePicker

# ==========================================
# 5. CHARGEMENT KV ET LOGIQUE APP
# ==========================================
# Chargement du fichier séparé
Builder.load_file('notifications.kv')

# --- CONFIGURATION DIMENSION ET ENVIRONNEMENT ---
if platform == 'android':
    pass 
else:
    # Taille standard smartphone pour le test sur PC
    Window.size = (360, 640)

# --- CONFIGURATION RÉSEAU ---
SERVER_URL = "https://tontineserveur.pythonanywhere.com" # Utilise TON nom d'utilisateur
API_KEY = "LUCKY_SECRET_99228_XYZ" # Ta clé secrète

# --- CONSTANTES ET SKILLS MÉTIER ---
OPERATEURS_PAR_PAYS = {
    "Togo": {"code": "+228", "iso": "TG", "ops": ["Mixx By Yas", "Flooz"]},
    "Bénin": {"code": "+229", "iso": "BJ", "ops": ["MTN", "Moov"]},
    "Côte d'Ivoire": {"code": "+225", "iso": "CI", "ops": ["Orange", "MTN", "Wave"]},
    "Burkina Faso": {"code": "+226", "iso": "BF", "ops": ["Orange", "Moov"]}
}

def get_db_path():
    """Détermine le chemin d'écriture sécurisé selon la plateforme."""
    db_name = 'lucky_data.db'
    if platform == 'android':
        try:
            from android.storage import app_context
            path = app_context.getFilesDir().getAbsolutePath()
            return os.path.join(path, db_name)
        except Exception as e:
            return db_name
    return db_name

class NotificationManager:
    def __init__(self, filename="user_data.json"):
        self.filename = filename
        self.data = self._load_data()

    def _load_data(self):
        if not os.path.exists(self.filename):
            return {"notifications": []}
        try:
            with open(self.filename, 'r', encoding='utf-8') as f:
                return json.load(f)
        except:
            return {"notifications": []}

    def add_notification(self, title, message, type_notif="info"):
        """ Ajoute une notification (tirage, transaction, promo) """
        new_notif = {
            "id": len(self.data["notifications"]) + 1,
            "title": title,
            "message": message,
            "date": datetime.now().strftime("%d/%m/%Y %H:%M"),
            "type": type_notif,
            "is_read": False
        }
        # On insère au début pour que le plus récent soit en haut (style SMS)
        self.data["notifications"].insert(0, new_notif)
        
        # Sécurité : On limite à 100 notifications pour éviter de ralentir l'APK
        if len(self.data["notifications"]) > 100:
            self.data["notifications"].pop()
            
        self._save()

    def _save(self):
        with open(self.filename, 'w', encoding='utf-8') as f:
            json.dump(self.data, f, indent=4)

    def get_unread_count(self):
        return len([n for n in self.data["notifications"] if not n["is_read"]])

    def mark_as_read(self, notif_id):
        for n in self.data["notifications"]:
            if n["id"] == notif_id:
                n["is_read"] = True
                break
        self._save()

class NotificationItem(MDBoxLayout):
    """ Classe représentant une ligne de notification dans la liste """
    title = StringProperty()
    message = StringProperty()
    date = StringProperty()
    is_read = BooleanProperty(False)

    def __init__(self, **kwargs):
        super().__init__(**kwargs)
        # La structure visuelle est définie dans ton fichier notifications.kv
        
    def press_callback(self, instance):
        # Cette fonction est appelée lors du clic (défini dans le .kv)
        pass

class SplashScreen(Screen):
    def on_enter(self):
        # Attendre 3 secondes puis passer à l'écran de connexion
        Clock.schedule_once(self.switch_to_login, 3)

    def switch_to_login(self, dt):
        self.manager.current = 'login'

# --- CLASSES DES ÉCRANS ---
class LoginScreen(Screen):
    pass

class RegisterScreen(Screen):
    pass


from kivymd.uix.card import MDCard

class TontineCard(MDCard):
    tontine_id = StringProperty("")
    tontine_name = StringProperty("")
    mise = StringProperty("")
    participants = NumericProperty(0)
    max_participants = NumericProperty(10)
    
    def __init__(self, callback_join, **kwargs):
        super().__init__(**kwargs)
        self.callback_join = callback_join
        # Design forcé en Python pour éviter les crashs KV
        self.orientation = "vertical"
        self.size_hint_y = None
        self.height = "120dp" # Un peu plus haut pour l'esthétique
        self.padding = "10dp"
        self.radius = [15,]

class DashboardScreen(Screen):
    # --- TES PROPRIÉTÉS EXISTANTES (CONSERVÉES) ---
    pot_total = StringProperty("0")
    offre_actuelle = StringProperty("0")
    user_fullname = StringProperty("Chargement...")
    user_balance = StringProperty("0 F")
    announcement_text = StringProperty("Vérification des annonces...")
    unread_count = NumericProperty(0)
    is_parrain = BooleanProperty(False)
    lucky_status_text = StringProperty("En attente d'enchère...")
    timer_active = BooleanProperty(False)
    is_lucky_visible = BooleanProperty(False)
    winner_name = StringProperty("") 
    group_name = StringProperty("Chargement du groupe...")
    next_round_day = StringProperty("---")
    
    # --- AJOUT CRUCIAL POUR ÉVITER LE CRASH ---
    luck_points = StringProperty("0") # Cette ligne manquait et causait l'AttributeError

    result_dialog = None
    loading_dialog = None

    # --- 1. NOUVELLE FONCTION : L'INITIALISATION (À AJOUTER ICI) ---
    def __init__(self, **kwargs):
        super().__init__(**kwargs)
        self.notif_manager = NotificationManager()
        # Définition de la cible (Exemple : tirage dans 24h pour le test APK réel)
        # Dans une vraie app, cette date viendrait de ta base de données/API
        self.target_time = datetime.now() + timedelta(hours=24)
        self.next_round_day = self.target_time.strftime("%d/%m/%Y %H:%M")
        
        # Activation de la visibilité pour le test
        self.is_lucky_visible = True 
        
        # Démarrage du thread de surveillance en arrière-plan
        # daemon=True est vital pour que le thread s'arrête si on ferme l'app
        threading.Thread(target=self._run_timer_thread, daemon=True).start()
    
    def on_enter(self):
        """ 
        Point d'entrée automatique : Se déclenche quand l'écran s'affiche.
        Initialise les données locales et lance la synchronisation serveur.
        """
        # 1. Valeurs de secours (Évite l'affichage vide au chargement)
        if not hasattr(self, 'pot_total') or not self.pot_total:
            self.pot_total = "750 000"
        if not hasattr(self, 'offre_actuelle') or not self.offre_actuelle:
            self.offre_actuelle = "520 000"
        
        # 2. Lancement de la mise à jour serveur immédiate
        self.refresh_user_data()
        
        # AJOUT : Charger les tontines dynamiquement
        self.load_dynamic_tontines()
        # 3. Programmation d'une boucle de rafraîchissement (toutes les 60 secondes)
        # On utilise Clock.schedule_interval pour garder le solde à jour
        self.refresh_event = Clock.schedule_interval(lambda dt: self.refresh_user_data(), 60)

    def on_leave(self):
        """ 
        Nettoyage : Se déclenche quand on quitte l'écran.
        Arrête la boucle pour économiser la batterie et les ressources serveur.
        """
        if hasattr(self, 'refresh_event'):
            Clock.unschedule(self.refresh_event)

    def refresh_user_data(self):
        """
        VERSION FINALE PROFESSIONNELLE
        Nettoyage complet des variables pour supprimer les alertes VS Code.
        """
        import threading
        import requests
        from kivy.app import App
        from kivy.clock import Clock
        
        app = App.get_running_app()
        
        # On récupère la valeur actuelle (Dynamique)
        current_phone = getattr(app, 'phone_utilisateur', getattr(app, 'user_phone', None))
        
        if not current_phone:
            print("ERREUR : Aucun utilisateur identifié.")
            return

        # Correction cruciale : On utilise 'phone_to_call' partout à l'intérieur
        def fetch_logic(phone_to_call):
            try:
                headers = {
                    "Authorization": API_KEY,
                    "X-API-KEY": API_KEY,
                    "Content-Type": "application/json"
                }
                
                response = requests.post(
                    f"{SERVER_URL}/api/get_user_info",
                    json={"phone": phone_to_call}, # <--- CORRIGÉ
                    headers=headers,
                    timeout=10
                )
                
                if response.status_code == 200:
                    data = response.json()
                    Clock.schedule_once(lambda dt: self.apply_data_update(data))
                else:
                    print(f"Erreur Serveur: {response.status_code}")
                    
            except Exception as e:
                print(f"Erreur Réseau: {e}")

        # On injecte 'current_phone' dans le thread
        threading.Thread(target=fetch_logic, args=(current_phone,), daemon=True).start()

    def load_dynamic_tontines(self):
        """
        VERSION PROFESSIONNELLE INTÉGRALE - LUCKY TONTINE
        Récupération robuste des groupes avec isolation des threads et 
        gestion multi-format des données serveur.
        """
        import threading
        import requests
        from kivy.clock import Clock

        def fetch_tontines():
            try:
                # 1. Configuration des Headers de sécurité
                headers = {
                    "Authorization": API_KEY, 
                    "X-API-KEY": API_KEY,
                    "Content-Type": "application/json"
                }
                
                # 2. Requête vers l'API
                response = requests.get(
                    f"{SERVER_URL}/api/get_available_tontines", 
                    headers=headers, 
                    timeout=12  # Timeout légèrement augmenté pour les réseaux mobiles
                )
                
                # 3. Analyse de la réponse
                if response.status_code == 200:
                    raw_data = response.json()
                    
                    # LOGIQUE D'EXTRACTION ROBUSTE :
                    # On gère le cas où le serveur renvoie un dictionnaire {'tontines': [...]}
                    # ou directement une liste [...]
                    if isinstance(raw_data, dict) and 'tontines' in raw_data:
                        tontines_list = raw_data['tontines']
                    elif isinstance(raw_data, list):
                        tontines_list = raw_data
                    else:
                        tontines_list = []
                        print("[LUCKY_LOG] Format JSON inattendu : liste vidée par sécurité.")

                    # 4. Synchronisation avec l'interface graphique (Thread Principal)
                    # On ne simplifie pas : on passe la liste complète à l'afficheur
                    Clock.schedule_once(lambda dt: self.display_tontines(tontines_list))
                
                elif response.status_code == 403:
                    print("[LUCKY_LOG] Sécurité : Clé API invalide ou accès refusé par le serveur.")
                
                elif response.status_code == 404:
                    print("[LUCKY_LOG] Endpoint introuvable : Vérifiez l'URL de l'API.")
                
                else:
                    print(f"[LUCKY_LOG] Erreur Serveur non gérée : Code {response.status_code}")

            except requests.exceptions.Timeout:
                print("[LUCKY_LOG] Délai d'attente dépassé : Le serveur est trop lent à répondre.")
            
            except requests.exceptions.ConnectionError:
                print("[LUCKY_LOG] Erreur de connexion : Vérifiez votre accès internet.")
                
            except Exception as e:
                # On utilise str(e) pour capturer le message d'erreur réel sans crash
                print(f"[LUCKY_LOG] Exception Critique non prévue : {str(e)}")

        # 5. Lancement du Thread (Daemon pour ne pas bloquer la fermeture de l'APK)
        thread = threading.Thread(target=fetch_tontines, daemon=True)
        thread.start()
    
    def submit_auction_result(self, tontine_id, montant_saisi, winner_phone):
        """
        Lancé par le Parrain. Distribue l'argent et programme le pot de chance.
        """
        import requests
        from kivymd.toast import toast
        from kivy.clock import Clock
        
        try:
            valeur_enchere = float(montant_saisi)
        except ValueError:
            toast("Erreur : Montant invalide")
            return

        def process():
            headers = {"X-API-KEY": API_KEY, "Content-Type": "application/json"}
            payload = {
                "tontine_id": tontine_id,
                "enchere": valeur_enchere,
                "winner_phone": winner_phone
            }
            
            try:
                # 1. DISTRIBUTION IMMÉDIATE
                response = requests.post(
                    f"{SERVER_URL}/api/distribute_auction",
                    json=payload, headers=headers, timeout=15
                )
                
                if response.status_code == 200:
                    Clock.schedule_once(lambda dt: toast("Distribution OK ! Pot de chance dans 10 min."))
                    self.refresh_user_data()
                    
                    # 2. LANCEMENT DU COMPTE À REBOURS (Automatique)
                    # On attend 600 secondes (10 min) puis on appelle la route du tirage
                    Clock.schedule_once(lambda dt: self.auto_trigger_luck(tontine_id), 600)
                else:
                    msg = response.json().get('error', 'Erreur')
                    Clock.schedule_once(lambda dt: toast(msg))
            except Exception as e:
                print(f"Erreur Serveur : {e}")

        import threading
        threading.Thread(target=process, daemon=True).start()

    def auto_trigger_luck(self, tontine_id):
        """
        Appelle le serveur pour le tirage au sort automatique.
        """
        import requests
        headers = {"X-API-KEY": API_KEY, "Content-Type": "application/json"}
        
        try:
            requests.post(
                f"{SERVER_URL}/api/trigger_pot_chance",
                json={"tontine_id": tontine_id},
                headers=headers, timeout=10
            )
            # Rafraîchir pour voir si on a gagné le pot de chance !
            self.refresh_user_data()
        except:
            pass

    def apply_data_update(self, data):
        """Met à jour les variables de l'interface avec protection contre les types erronés."""
        # SÉCURITÉ : Si le serveur renvoie une erreur (string), on arrête tout
        if not isinstance(data, dict):
            print(f"Erreur data_update : attendait dictionnaire, reçu {type(data)}")
            return

        # 1. Mise à jour du nom complet
        self.user_fullname = data.get('fullname', self.user_fullname)
        self.announcement_text = data.get('announcement', self.announcement_text)
        
        # 2. Formatage du Solde
        if 'balance' in data:
            try:
                solde_brut = int(data['balance'])
                self.user_balance = f"{solde_brut:,}".replace(",", " ") + " F"
            except (ValueError, TypeError):
                self.user_balance = str(data['balance']) + " F"
            
        # 3. Points et Chiffres
        self.luck_points = str(data.get('luck_points', self.luck_points))
        self.pot_total = str(data.get('pot_total', self.pot_total))
        self.offre_actuelle = str(data.get('offre_actuelle', self.offre_actuelle))
        
        print("Dashboard mis à jour avec succès.")

    def display_tontines(self, tontines_list):
        try:
            # SÉCURITÉ CHIRURGICALE : On vérifie l'existence de l'ID avant d'agir
            if 'container' not in self.ids:
                print("[LUCKY_DEBUG] ERREUR : L'ID 'container' est absent du fichier KV.")
                return

            container = self.ids.container
            container.clear_widgets()
            
            # SÉCURITÉ 1 : Vérifier si tontines_list est bien une liste
            if not isinstance(tontines_list, list):
                print(f"ALERTE : Le serveur a renvoyé un texte au lieu d'une liste : {tontines_list}")
                return

            for t in tontines_list:
                # SÉCURITÉ 2 : Vérifier que l'élément 't' est bien un dictionnaire
                if not isinstance(t, dict):
                    continue

                t_id = t.get('id') or t.get('tontine_id') or "0"
                t_nom = t.get('name', 'Groupe sans nom')
                t_amount = t.get('amount', 0)
                t_current = t.get('current_members', 0)
                t_max = t.get('max_members', 10)

                card = TontineCard(
                    tontine_id=str(t_id),
                    tontine_name=t_nom,
                    mise=f"{t_amount:,}".replace(",", " "),
                    participants=t_current,
                    max_participants=t_max,
                    callback_join=self.join_tontine_logic
                )
                container.add_widget(card)
                
            print("Mise à jour visuelle des tontines réussie.")

        except Exception as e:
            print(f"Erreur lors de l'affichage UI : {e}")

    def join_tontine_logic(self, tontine_id):
        """
        Envoie l'ordre de mise au serveur de manière robuste.
        """
        import requests
        from kivymd.toast import toast
        
        app = MDApp.get_running_app()
        phone = getattr(app, 'phone_utilisateur', "96110013")

        def send_bet():
            try:
                headers = {"X-API-KEY": API_KEY, "Content-Type": "application/json"}
                payload = {"phone": phone, "tontine_id": tontine_id}
                
                response = requests.post(
                    f"{SERVER_URL}/api/participate_tontine",
                    json=payload,
                    headers=headers,
                    timeout=10
                )
                
                if response.status_code == 200:
                    res = response.json()
                    if res.get("action") == "tontine_completed":
                        Clock.schedule_once(lambda dt: toast(f"Tirage terminé ! Gagnant: {res['winner']}"))
                    else:
                        Clock.schedule_once(lambda dt: toast("Mise validée !"))
                    
                    # Rafraîchir le solde immédiatement
                    self.refresh_user_data()
                else:
                    msg = response.json().get('message', 'Erreur')
                    Clock.schedule_once(lambda dt: toast(msg))
                    
            except Exception as e:
                print(f"Erreur bet: {e}")

        import threading
        threading.Thread(target=send_bet, daemon=True).start()

    def _run_timer_thread(self):
        """ Boucle de calcul haute précision pour le temps restant """
        while True:
            now = datetime.now()
            remainder_seconds = (self.target_time - now).total_seconds()
            
            if remainder_seconds > 0:
                total_seconds = int(remainder_seconds)
                hours, remainder = divmod(total_seconds, 3600)
                minutes, seconds = divmod(remainder, 60)
                time_str = f"Tirage dans {hours:02d}h {minutes:02d}m {seconds:02d}s"
                
                Clock.schedule_once(lambda dt: self._update_lucky_ui(time_str, True))
            else:
                # --- ACTION À LA FIN DU CHRONO ---
                # 1. On informe l'UI que c'est fini
                Clock.schedule_once(lambda dt: self._update_lucky_ui("Annonce du gagnant...", False))
                # 2. On déclenche la sélection réelle
                Clock.schedule_once(lambda dt: self.trigger_winner_selection())
                break 
                
            time.sleep(1)

    # --- 3. NOUVELLE FONCTION : MISE À JOUR UI (À AJOUTER ICI) ---
    def _update_lucky_ui(self, text, active):
        """ Applique les changements sur l'interface sans freeze """
        self.lucky_status_text = text
        self.timer_active = active
    
    def trigger_winner_selection(self):
        self.winner_name = "SOSSOU K. Dérick Richard"
        
        # SAUVEGARDE AUTOMATIQUE DANS L'HISTORIQUE FUSIONNÉ
        self.notif_manager.add_notification(
            title="🏆 Résultat Tirage",
            message=f"Le Pot de Chance a été remporté par {self.winner_name}.",
            type_notif="tirage"
        )
        
        # Mettre à jour le badge de la cloche sur l'UI
        self.update_notif_badge()
        
        self.animate_winner_badge()
        Clock.schedule_once(lambda dt: self.show_winner_dialog(), 0.5)

    def update_notif_badge(self):
        count = self.notif_manager.get_unread_count()
        # On accède à l'id de la cloche dans le KV pour changer son texte ou badge
        self.ids.notif_badge.text = str(count) if count > 0 else ""
        self.ids.notif_badge.opacity = 1 if count > 0 else 0

    def animate_winner_badge(self):
        """ Animation de pulsation pour le badge du gagnant """
        if self.ids.lucky_badge:
            # Animation : agrandir et changer de couleur (vert succès)
            anim = Animation(md_bg_color=(0, 0.8, 0.4, 1), duration=0.5) + \
                   Animation(md_bg_color=(0, 0.6, 0.3, 1), duration=0.5)
            
            # On fait boucler l'animation 5 fois
            anim.repeat = True
            anim.start(self.ids.lucky_badge)
            
            # Mise à jour du texte final
            self.lucky_status_text = f"Félicitations : {self.winner_name} !"
        
    def show_winner_dialog(self):
        """ Affiche la popup moderne et déclenche une vibration Android précise """
        
        # 1. Vibration de notification (0.5s = durée standard "Succès")
        try:
            if vibrator:
                vibrator.vibrate(0.5)
        except Exception as e:
            # Sécurité pour les tests sur Windows/Mac
            print(f"DEBUG: Vibration ignorée sur PC: {e}")

        # 2. Création du dialogue avec mise en forme riche (Markup)
        if not self.result_dialog:
            self.result_dialog = MDDialog(
                title="[color=1a247d]🎉 Tirage Terminé ![/color]",
                text=(
                    f"Le tirage au sort vient de désigner un gagnant.\n\n"
                    f"Félicitations à [b][color=00994d]{self.winner_name}[/color][/b] "
                    f"qui remporte le Pot de Chance ce tour-ci !"
                ),
                type="custom",
                auto_dismiss=False, # Force l'utilisateur à cliquer sur le bouton pour fermer
                buttons=[
                    MDFlatButton(
                        text="GÉNIAL",
                        theme_text_color="Custom",
                        text_color=(0.1, 0.14, 0.49, 1),
                        on_release=lambda x: self.result_dialog.dismiss()
                    ),
                ],
            )
        
        # 3. Ouverture du dialogue
        self.result_dialog.open()

    def show_loading(self, message="Traitement en cours..."):
        """Affiche un dialogue de chargement optimisé et persistant."""
        # 1. On vérifie si le dialogue existe déjà pour éviter les fuites mémoire
        if not self.loading_dialog:
            self.loading_dialog = MDDialog(
                type="custom",
                content_cls=MDBoxLayout(
                    MDSpinner(
                        size_hint=(None, None),
                        size=("30dp", "30dp"),
                        active=True,
                        palette=[
                            [0.1, 0.5, 0.1, 1],    # Vert Tirage
                            [0.1, 0.14, 0.49, 1], # Bleu Lucky Tontine
                        ],
                    ),
                    MDLabel(
                        text=message,
                        halign="left",
                        theme_text_color="Primary",
                        font_style="Body2",
                    ),
                    orientation="horizontal",
                    spacing="15dp",
                    padding="10dp",
                    size_hint_y=None,
                    height="50dp",
                ),
                auto_dismiss=False, # Sécurité : bloque l'écran pendant l'action
            )
        
        # 2. Mise à jour du message si le dialogue existe déjà
        self.loading_dialog.content_cls.children[0].text = message
        
        # 3. Ouverture effective
        self.loading_dialog.open()

    def dismiss_loading(self):
        """Ferme proprement le dialogue de chargement."""
        if self.loading_dialog:
            self.loading_dialog.dismiss()
    
    def open_notification_panel(self):
        """ Ouvre le centre de notifications style WhatsApp """
        # 1. Récupération sécurisée des données
        notifications = self.notif_manager.data.get("notifications", [])
        
        # 2. Création du ModalView
        self.notif_view = MDModalView(
            size_hint=(0.95, 0.85),
            background_color=[0, 0, 0, 0], # Transparent pour laisser le design au MDBoxLayout
            auto_dismiss=True # Permet de fermer en cliquant à côté
        )
        
        # 3. Conteneur Principal avec bords arrondis (Style moderne)
        content = MDBoxLayout(
            orientation='vertical', 
            md_bg_color=(1, 1, 1, 1), 
            radius=[20, 20, 0, 0]
        )
        
        # --- Header du panneau ---
        header = MDBoxLayout(
            adaptive_height=True, 
            padding="12dp", 
            md_bg_color=(0.1, 0.14, 0.49, 1), # Ton bleu spécifique
            radius=[20, 20, 0, 0]
        )
        header.add_widget(MDLabel(
            text="Notifications", 
            bold=True, 
            theme_text_color="Custom", 
            text_color=(1, 1, 1, 1),
            font_style="H6"
        ))
        
        # On ajoute le bouton supprimer UNIQUEMENT s'il y a des notifs
        if notifications:
            btn_clear = MDIconButton(
                icon="delete-sweep", 
                theme_text_color="Custom", 
                text_color=(1, 1, 1, 1)
            )
            btn_clear.bind(on_release=lambda x: self.confirm_delete_all(self.notif_view))
            header.add_widget(btn_clear)
        
        content.add_widget(header)

        # --- Corps de la liste (Scrollable) ---
        scroll = MDScrollView()
        list_layout = MDBoxLayout(
            orientation='vertical', 
            adaptive_height=True, 
            padding="10dp", 
            spacing="10dp"
        )

        if not notifications:
            # État Vide Moderne
            empty_box = MDBoxLayout(
                orientation='vertical', 
                adaptive_height=True, 
                padding=[0, 100, 0, 0], 
                spacing="10dp"
            )
            empty_box.add_widget(MDIcon(
                icon="bell-off-outline", 
                halign="center", 
                font_size="64sp", 
                theme_text_color="Hint"
            ))
            empty_box.add_widget(MDLabel(
                text="Aucune notification", 
                halign="center", 
                theme_text_color="Hint"
            ))
            list_layout.add_widget(empty_box)
        else:
            # On affiche les notifs (les plus récentes en premier)
            for n in notifications:
                item = NotificationItem(
                    title=n["title"],
                    message=n["message"],
                    date=n["date"],
                    is_read=n["is_read"]
                )
                item.bind(on_release=lambda x, nid=n["id"]: self.read_notif(nid, self.notif_view))
                list_layout.add_widget(item)

        scroll.add_widget(list_layout)
        content.add_widget(scroll)
        
        self.notif_view.add_widget(content)
        self.notif_view.open()

    def read_notif(self, notif_id, view):
        """ Marque comme lu et ferme le panneau """
        self.notif_manager.mark_as_read(notif_id)
        self.unread_count = self.notif_manager.get_unread_count()
        view.dismiss()
        # Optionnel: on peut aussi simplement rafraîchir le badge sans fermer

    def confirm_delete_all(self, modal_view):
        """ Fenêtre de confirmation """
        self.dialog = MDDialog(
            title="Supprimer tout ?",
            text="Voulez-vous effacer tout votre historique de notifications ?",
            buttons=[
                MDFlatButton(text="ANNULER", on_release=lambda x: self.dialog.dismiss()),
                MDRaisedButton(
                    text="EFFACER", 
                    md_bg_color=(1, 0, 0, 1),
                    on_release=lambda x: self.delete_all_action(modal_view)
                ),
            ],
        )
        self.dialog.open()

    def delete_all_action(self, modal_view):
        """ Action finale de suppression """
        self.notif_manager.data["notifications"] = []
        self.notif_manager._save()
        self.unread_count = 0
        self.dialog.dismiss()
        modal_view.dismiss()
        toast("Historique effacé") # Utilise le toast natif de KivyMD

    def show_toast(self, text, color="info"):
        
        # Dictionnaire de couleurs pour tes alertes
        colors = {
            "success": "#2E7D32",
            "error": "#D32F2F",
            "info": "#1976D2"
        }
        
        # Version compatible KivyMD 1.2.0+
        # On crée la snackbar, et on lui donne son texte APRÈS ou via MDLabel interne
        snackbar = Snackbar(
            bg_color=get_color_from_hex(colors.get(color, "#333333")),
            duration=3
        )
        # On ajoute le texte comme un enfant du layout de la snackbar
        from kivymd.uix.label import MDLabel
        snackbar.add_widget(
            MDLabel(
                text=text,
                theme_text_color="Custom",
                text_color=(1, 1, 1, 1), # Blanc
                padding=[dp(20), dp(10)]
            )
        )
        snackbar.open()
    
    def activer_enchere(self):
        """Active l'affichage du bloc d'enchères sur l'interface."""
        self.is_lucky_visible = True
    
    def update_dashboard_values(self, nouveau_pot, nouvelle_offre):
        """
        Fonction pour mettre à jour les valeurs proprement.
        Utilisez cette fonction pour changer les chiffres.
        """
        self.pot_total = str(nouveau_pot)
        self.offre_actuelle = str(nouvelle_offre)
    
    def animate_scroll(self, *args):
        # On récupère le ScrollView par son ID défini dans le KV
        scroll_view = self.ids.lucky_scroll 
        
        # Animation : Va à droite (1) en 5 sec, puis revient à gauche (0) en 5 sec
        anim = Animation(scroll_x=1, duration=5) + Animation(scroll_x=0, duration=5)
        anim.repeat = True # Boucle infinie comme à la télé
        anim.start(scroll_view)

    def refresh_dashboard_data(self):
        """Récupère les informations depuis le serveur de manière sécurisée."""
        try:
            # Simulation des données (Sera remplacé par app.send_to_server plus tard)
            self.user_fullname = "SOSSOU K Dérick R"
            self.user_balance = "125.500 F"
            self.announcement_text = "Le serveur prépare le tirage de fidélité."
            self.is_parrain = True 
            
            # On vérifie si un compte à rebours est en cours sur le serveur
            self.consulter_statut_tirage()
            
        except Exception as e:
            print(f"[Dashboard Error] Échec de la mise à jour : {str(e)}")

    def consulter_statut_tirage(self):
        """
        Simule la réponse du serveur et démarre le moteur de temps.
        """
        try:
            minutes_du_serveur = 10 # Simulation d'une réponse serveur
            
            if minutes_du_serveur > 0:
                self.start_lucky_countdown(minutes_du_serveur)
            else:
                self.is_lucky_visible = False
        except Exception as e:
            print(f"[Timer Error] Erreur de synchronisation : {e}")

    def update_lucky_draw_timer(self, remaining_time):
        """Mise à jour de l'UI et de la visibilité du badge."""
        if remaining_time > 0:
            self.timer_active = True
            self.lucky_status_text = f"Pot de Chance : {remaining_time} min"
            # On rend le widget visible uniquement ici
            self.is_lucky_visible = True
        else:
            self.timer_active = False
            self.lucky_status_text = ""
            # On cache totalement le widget
            self.is_lucky_visible = False

    def on_lucky_draw_result(self, winner_name, amount):
        """
        Méthode robuste appelée automatiquement par le serveur via le 
        gestionnaire de réseau quand le tirage est fini.
        """
        
        # 1. Sécurité : Si un dialogue est déjà ouvert, on le ferme proprement
        if self.result_dialog:
            self.result_dialog.dismiss()
            self.result_dialog = None

        # 2. Création du dialogue de victoire
        self.result_dialog = MDDialog(
            title="🎉 RÉSULTAT POT DE CHANCE 🎉",
            text=(
                f"Le serveur a désigné le gagnant fidèle :\n\n"
                f"FÉLICITATIONS À : [b]{winner_name}[/b]\n"
                f"GAIN : [b]{amount} F[/b]\n\n"
                "Le crédit sera effectif sur votre solde dans 2 minutes."
            ),
            buttons=[
                MDRaisedButton(
                    text="GÉNIAL !",
                    md_bg_color=(0, 0.6, 0.3, 1), # Vert réussite
                    on_release=lambda x: self.result_dialog.dismiss()
                ),
            ],
            auto_dismiss=False, # L'utilisateur doit cliquer sur le bouton
            radius=[20, 7, 20, 7],
        )
        
        # 3. Ouverture avec un léger délai pour s'assurer que l'UI est prête
        Clock.schedule_once(lambda dt: self.result_dialog.open(), 0.5)

    def start_lucky_countdown(self, minutes_restantes):
        """
        Lance le compte à rebours de manière sécurisée.
        """
        self.temps_interne = int(minutes_restantes) # Stockage du chiffre pur
        
        # On annule tout timer précédent pour éviter les doublons (conflits mémoire)
        Clock.unschedule(self.logic_de_calcul)
        
        # On lance le calcul toutes les 60 secondes
        Clock.schedule_interval(self.logic_de_calcul, 60)
        
        # Mise à jour immédiate de l'affichage
        self.update_lucky_draw_timer(self.temps_interne)

    def logic_de_calcul(self, dt):
        """
        L'algorithme qui réduit le temps et surveille la fin du tour.
        """
        if self.temps_interne > 0:
            self.temps_interne -= 1
            self.update_lucky_draw_timer(self.temps_interne)
        else:
            # Le temps est écoulé !
            Clock.unschedule(self.logic_de_calcul)
            self.lucky_status_text = "Tirage en cours..."
            # Ici, on appellera la fonction pour demander le gagnant au serveur
            return False # Arrête définitivement le timer
    
    def lancer_processus_tirage(self):
        """Action déclenchée lors de l'appui sur le bouton Tirage."""
        # 1. On montre le chargement immédiatement
        self.show_loading("Le serveur vérifie les participations...")

        # 2. On simule un délai réseau (ex: 2 secondes) pour tester la robustesse
        # Dans la vraie version, ce sera remplacé par l'appel réseau
        Clock.schedule_once(self.finaliser_tirage_test, 2)

    def finaliser_tirage_test(self, dt):
        """Simule la réception de la réponse après le chargement."""
        # 3. On cache le chargement une fois la réponse reçue
        self.dismiss_loading()
        
        # 4. On affiche le résultat final (votre dialogue de victoire)
        self.on_lucky_draw_result("SOSSOU Komlan Dérick Richard", "25.000")
    
class CreateGroupScreen(Screen):
    # Propriétés pour l'interface
    group_frequency = StringProperty("Hebdomadaire")
    first_due_date = StringProperty("Cliquer pour la date")
    invite_code_generated = StringProperty("---")

    def on_enter(self):
        """Réinitialise le formulaire à chaque entrée sur l'écran"""
        self.ids.group_name.text = ""
        self.ids.target_amount.text = ""
        self.group_frequency = "Hebdomadaire"
        self.first_due_date = "Cliquer pour la date"

    def show_date_picker(self):
        """Version ultra-robuste avec gestion d'erreur totale"""
        try:
            # On tente l'import local pour éviter les conflits globaux
            try:
                from kivymd.uix.pickers import MDDatePicker
            except:
                from kivymd.uix.datepicker import MDDatePicker

            date_dialog = MDDatePicker(
                title="DATE DE DÉBUT",
                min_date=datetime.now().date(),
                # CLÉ DU PROBLÈME : On force le mode 'picker' au lieu de 'input'
                type="picker" 
            )
            date_dialog.bind(on_save=self.on_save_date)
            date_dialog.open()
            
        except Exception as e:
            # Si l'outil crash encore, on bascule sur une saisie manuelle sécurisée
            self.show_manual_date_dialog()

    def on_save_date(self, instance, value, date_range):
        self.temp_date = value.strftime("%Y-%m-%d")
        instance.dismiss()
        # On enchaîne avec l'heure après un court délai
        from kivy.clock import Clock
        Clock.schedule_once(lambda dt: self.show_time_picker(), 0.2)

    def show_time_picker(self):
        try:
            try:
                from kivymd.uix.pickers import MDTimePicker
            except:
                from kivymd.uix.timepicker import MDTimePicker
                
            time_dialog = MDTimePicker()
            # Forcer une taille pour le dialogue si nécessaire
            time_dialog.bind(on_save=self.on_save_time)
            time_dialog.open()
        except:
            self.show_manual_time_dialog()

    def on_save_time(self, instance, time_value):
        heure_format = time_value.strftime("%H:%M")
        self.first_due_date = f"{self.temp_date} à {heure_format}"
        instance.dismiss()

    def show_manual_date_dialog(self):
        """Solution de secours si le widget KivyMD refuse de s'afficher correctement"""
        from kivymd.uix.textfield import MDTextField
        self.manual_input = MDTextField(hint_text="JJ/MM/AAAA", text=datetime.now().strftime("%d/%m/%Y"))
        
        self.diag = MDDialog(
            title="Saisir la date",
            type="custom",
            content_cls=self.manual_input,
            buttons=[
                MDFlatButton(text="ANNULER", on_release=lambda x: self.diag.dismiss()),
                MDRaisedButton(text="VALIDER", on_release=self.set_manual_date)
            ]
        )
        self.diag.open()

    def set_manual_date(self, *args):
        self.first_due_date = self.manual_input.text
        self.diag.dismiss()

    def valider_et_preparer(self):
        """Vérification stricte avant confirmation"""
        name = self.ids.group_name.text.strip()
        amount = self.ids.target_amount.text.strip()
        
        if len(name) < 4:
            MDApp.get_running_app().show_toast("Nom du groupe trop court", "error")
            return
        if not amount.isdigit() or int(amount) < 500:
            MDApp.get_running_app().show_toast("Montant invalide (min 500)", "error")
            return
        if self.first_due_date == "Cliquer pour la date":
            MDApp.get_running_app().show_toast("Veuillez choisir une date", "error")
            return

        self.afficher_dialogue_confirmation(name, amount)

    def afficher_dialogue_confirmation(self, name, amount):
        """Affiche le résumé avant l'envoi final"""

        self.confirm_dialog = MDDialog(
            title="Confirmer la création ?",
            text=f"Groupe: {name}\nCotisation: {amount} FCFA\nDébut: {self.first_due_date}",
            buttons=[
                MDFlatButton(text="MODIFIER", on_release=lambda x: self.confirm_dialog.dismiss()),
                MDRaisedButton(
                    text="CRÉER LE GROUPE",
                    md_bg_color=(0.1, 0.14, 0.49, 1),
                    on_release=lambda x: self.executer_creation_serveur(name, amount)
                ),
            ],
        )
        self.confirm_dialog.open()

    def executer_creation_serveur(self, name, amount):
        """Envoi final au serveur avec Authentification et URL correcte"""
        self.confirm_dialog.dismiss()
        import requests
        import threading
        app = MDApp.get_running_app()
        
        # 1. Préparation des données (Payload)
        payload = {
            "group_name": name,
            "amount": int(amount),
            "frequency": getattr(self, 'group_frequency', "Mensuel"),
            "start_timestamp": getattr(self, 'formatted_date_server', ""),
            "parrain_phone": getattr(app, 'phone_utilisateur', "96110013"), # Utilise le téléphone
            "type_tontine": "enchere_automatique"
        }

        def send():
            try:
                # 2. Ajout des HEADERS de sécurité (C'est ça qui enlève l'erreur 403)
                headers = {
                    "X-API-KEY": API_KEY, # Défini en haut de ton main.py
                    "Content-Type": "application/json"
                }
                
                # 3. Utilisation de la bonne URL serveur
                url = f"{SERVER_URL}/api/create_tontine"
                
                response = requests.post(url, json=payload, headers=headers, timeout=15)
                
                if response.status_code == 200:
                    # Si succès, on lance la suite sur le thread Kivy
                    result = response.json()
                    Clock.schedule_once(lambda dt: self.apres_creation_succes(None, result))
                else:
                    error_msg = f"Erreur {response.status_code}"
                    Clock.schedule_once(lambda dt: app.show_toast(error_msg, "error"))
                    
            except Exception as e:
                Clock.schedule_once(lambda dt: app.show_toast("Erreur de connexion", "error"))
                print(f"DEBUG CREATION : {e}")

        # Lancement en arrière-plan pour ne pas bloquer l'écran
        threading.Thread(target=send, daemon=True).start()
        app.show_toast("Création du groupe en cours...", "info")

    def apres_creation_succes(self, req, result):
        code = result.get("invite_code", "ERREUR")
        self.invite_code_generated = code
        
        # On génère le lien de parrainage (Remplacez par votre futur domaine)
        lien_parrainage = f"https://luckytontine.com/join?code={code}"

        self.final_dialog = MDDialog(
            title="🚀 GROUPE CRÉÉ !",
            text=f"CODE : [b]{code}[/b]\n\nLien : {lien_parrainage}",
            buttons=[
                MDFlatButton(
                    text="COPIER LE LIEN",
                    on_release=lambda x: self.copier_code(lien_parrainage)
                ),
                MDRaisedButton(
                    text="RETOUR AU DASHBOARD",
                    md_bg_color=(0.1, 0.14, 0.49, 1),
                    on_release=self.fermer_et_quitter
                ),
            ],
            auto_dismiss=False
        )
        self.final_dialog.open()

    def copier_code(self, texte_a_copier):
        """Copie le lien ou le code dans le presse-papier du téléphone"""
        Clipboard.copy(texte_a_copier)
        MDApp.get_running_app().show_toast("Lien copié dans le presse-papier !", "info")

    def fermer_et_quitter(self, *args):
        if hasattr(self, 'final_dialog'):
            self.final_dialog.dismiss()
        self.manager.current = 'dashboard'

class GroupDashboardScreen(MDScreen):
    def on_enter(self):
        # On récupère les infos du groupe depuis le serveur
        group_data = self.get_group_data_from_server() 
        current_user = MDApp.get_running_app().user_phone
        
        # VERIFICATION : Est-ce que l'utilisateur est le parrain ?
        if current_user == group_data['parrain_phone']:
            self.ids.admin_layout.opacity = 1
            self.ids.admin_layout.disabled = False
            print("Accès Admin accordé pour ce groupe")
        else:
            self.ids.admin_layout.opacity = 0
            self.ids.admin_layout.disabled = True
            print("Accès Membre simple")

class JoinGroupScreen(Screen):
    # Propriété pour l'affichage dynamique
    referral_code = StringProperty("")

    def on_enter(self):
        """À l'ouverture, on tente de détecter un code dans le presse-papier"""
        self.ids.group_code_input.text = ""
        contenu = Clipboard.paste()
        
        # Logique de détection intelligente : si le presse-papier contient "code="
        if "code=" in contenu:
            extrait = contenu.split("code=")[-1]
            self.ids.group_code_input.text = extrait
            MDApp.get_running_app().show_toast("Code détecté et inséré", "info")

    def valider_rejoindre(self):
        """Vérification du code avant envoi au serveur"""
        code = self.ids.group_code_input.text.strip().upper()
        app = MDApp.get_running_app()

        if not code or len(code) < 5:
            app.show_toast("Code d'invitation invalide", "error")
            return

        # Données à envoyer au serveur
        payload = {
            "invite_code": code,
            "user_id": getattr(app, 'user_id', "ID_UTILISATEUR"),
            "action": "join_request"
        }

        app.show_toast("Vérification du code...", "info")
        # Appel au serveur secondaire pour rejoindre
        app.send_to_server("/api/groups/join", payload, self.apres_demande_join)

    def apres_demande_join(self, req, result):
        """Réponse du serveur après tentative d'adhésion"""
        if result.get("status") == "success":
            msg = f"Demande envoyée ! Le parrain {result.get('parrain_name')} doit vous valider."
            self.afficher_succes(msg)
        else:
            erreur = result.get("message", "Code introuvable ou groupe complet")
            MDApp.get_running_app().show_toast(erreur, "error")

    def afficher_succes(self, message):
        from kivymd.uix.dialog import MDDialog
        from kivymd.uix.button import MDRaisedButton

        self.dialog = MDDialog(
            title="✅ DEMANDE ENREGISTRÉE",
            text=message,
            buttons=[
                MDRaisedButton(
                    text="OK",
                    on_release=lambda x: self.retour_dashboard()
                ),
            ],
            auto_dismiss=False
        )
        self.dialog.open()

    def retour_dashboard(self, *args):
        if hasattr(self, 'dialog'):
            self.dialog.dismiss()
        self.manager.current = 'dashboard'

# Widget pour aligner correctement les boutons à droite dans la liste
class RightDefinitionWidget(IRightBodyTouch, MDBoxLayout):
    pass

# Composant graphique pour chaque demande d'adhésion
class PendingUserItem(TwoLineAvatarIconListItem):
    user_name = StringProperty()
    group_name = StringProperty()
    user_id = StringProperty()
    group_id = StringProperty()
    # Ces callbacks seront liés dynamiquement dans le Screen
    callback_approve = None
    callback_reject = None

class ManageMembersScreen(Screen):
    def on_enter(self):
        """Chargement automatique des données dès que l'écran s'affiche"""
        self.fetch_pending_requests()

    def fetch_pending_requests(self):
        """Récupère la liste des demandes en attente via le serveur"""
        app = MDApp.get_running_app()
        payload = {
            "parrain_id": getattr(app, 'user_id', "ID_UTILISATEUR"),
            "status": "pending"
        }
        # Nettoyage de l'interface avant recharge pour éviter les doublons visuels
        if hasattr(self.ids, 'container_requests'):
            self.ids.container_requests.clear_widgets()
            
        app.send_to_server("/api/groups/pending_members", payload, self.display_requests)

    def display_requests(self, req, result):
        """Injecte les données du serveur dans l'interface graphique"""
        if result.get("status") == "success":
            requests = result.get("requests", [])
            
            if not requests:
                self.ids.container_requests.add_widget(
                    MDLabel(
                        text="Aucune demande d'adhésion en attente.",
                        halign="center",
                        theme_text_color="Hint",
                        padding_y="40dp"
                    )
                )
                return

            for req_data in requests:
                item = PendingUserItem(
                    user_name=req_data.get('user_name', "Inconnu"),
                    group_name=req_data.get('group_name', "Groupe"),
                    user_id=str(req_data.get('user_id', "")),
                    group_id=str(req_data.get('group_id', "")),
                    # On lie les fonctions de confirmation
                    callback_approve=lambda u, g, n: self.demander_confirmation(u, g, n, True),
                    callback_reject=lambda u, g, n: self.demander_confirmation(u, g, n, False)
                )
                self.ids.container_requests.add_widget(item)

    def demander_confirmation(self, user_id, group_id, user_name, is_approval):
        """Boîte de dialogue avec vérification du PIN pour la sécurité"""
        action_type = "ACCEPTER" if is_approval else "REFUSER"
        btn_color = (0, 0.6, 0, 1) if is_approval else (0.8, 0, 0, 1)
        
        # Champ de saisie du PIN intégré au dialogue
        self.pin_field = MDTextField(
            hint_text="Entrez votre PIN de sécurité",
            password=True,
            input_filter="int",
            max_text_length=4,
            halign="center"
        )

        self.confirm_dialog = MDDialog(
            title=f"{action_type} l'adhésion ?",
            type="custom",
            content_cls=self.pin_field,
            text=f"Action sur : [b]{user_name}[/b]",
            buttons=[
                MDFlatButton(text="ANNULER", on_release=lambda x: self.confirm_dialog.dismiss()),
                MDRaisedButton(
                    text="VALIDER",
                    md_bg_color=btn_color,
                    on_release=lambda x: self.valider_pin_et_envoyer(user_id, group_id, is_approval)
                ),
            ],
        )
        self.confirm_dialog.open()

    def valider_pin_et_envoyer(self, user_id, group_id, is_approval):
        """Vérifie le PIN localement avant l'envoi au serveur"""
        pin_code = self.pin_field.text
        if len(pin_code) != 4:
            MDApp.get_running_app().show_toast("Code PIN incomplet (4 chiffres)", "error")
            return

        self.confirm_dialog.dismiss()
        self.executer_decision(user_id, group_id, "approve" if is_approval else "reject", pin_code)

    def executer_decision(self, user_id, group_id, decision, pin):
        """Envoi final de la décision sécurisée au serveur"""
        app = MDApp.get_running_app()
        payload = {
            "target_user_id": user_id,
            "group_id": group_id,
            "decision": decision,
            "parrain_id": app.user_id,
            "auth_pin": pin # Le serveur vérifiera ce PIN
        }
        
        app.show_toast(f"Traitement en cours...", "info")
        app.send_to_server("/api/groups/validate_member", payload, self.after_server_response)

    def after_server_response(self, req, result):
        """Gestion de la réponse serveur et rafraîchissement"""
        if result.get("status") == "success":
            MDApp.get_running_app().show_toast("Action réussie !", "success")
            self.fetch_pending_requests()
        else:
            msg = result.get("message", "Erreur lors de l'opération")
            MDApp.get_running_app().show_toast(msg, "error")

    def retour_dashboard(self):
        self.manager.current = 'dashboard'

class ReportIssueScreen(Screen):
    def envoyer_signalement(self):
        raison = self.ids.report_reason.text
        details = self.ids.report_details.text.strip()
        
        if len(details) < 15:
            MDApp.get_running_app().show_toast("Veuillez donner plus de détails (min 15 car.)", "error")
            return

        app = MDApp.get_running_app()
        payload = {
            "sender_id": app.user_id,
            "reason": raison,
            "description": details,
            "group_context": getattr(self, 'current_group_id', "N/A")
        }
        
        app.send_to_server("/api/support/report", payload, self.apres_signalement)

    def apres_signalement(self, req, result):
        MDApp.get_running_app().show_toast("Signalement transmis avec succès.", "success")
        self.manager.current = "dashboard"

class HistoryScreen(Screen):
    transactions = ListProperty([])

class LuckyTontineManager(ScreenManager):
    pass

class RecoveryScreen(Screen):
    pass

# --- APPLICATION PRINCIPALE ---
class LuckyTontineApp(MDApp):

    # Liste des questions prédéfinies
    security_questions = [
        "Quel est le nom de votre premier animal ?",
        "Quelle est votre ville de naissance ?",
        "Le nom de votre école primaire ?",
        "Quel est le nom de votre meilleur ami d'enfance ?",
        "Quelle est la couleur de votre première voiture ?",
        "Quel est le nom de jeune fille de votre mère ?",
        "Quel est votre plat préféré ?",
        "Quel était votre surnom étant enfant ?",
        "Dans quelle ville vos parents se sont rencontrés ?",
        "Quel est le nom de votre premier employeur ?",
        "Quel est le nom de votre film préféré ?",
        "Quelle est la marque de votre premier téléphone ?",
        "Quel est le nom de votre cousin préféré ?",
        "Quel est votre chanteur préféré ?",
        "Quel est le nom de la rue où vous avez grandi ?"
    ]

    def on_start(self):
        """S'exécute au démarrage : crée la base de données si elle n'existe pas"""
        self.conn = sqlite3.connect("lucky_tontine.db")
        self.cursor = self.conn.cursor()
        self.cursor.execute('''
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                fullname TEXT,
                email TEXT UNIQUE,
                phone TEXT,
                password TEXT,
                sec_question TEXT,
                sec_answer TEXT
            )
        ''')
        self.conn.commit()

    def process_register(self, fullname, email, password, confirm, question, answer, phone):
        """Fonction d'inscription réelle et robuste"""
        # 1. Vérification des champs vides
        if not all([fullname, email, password, confirm, answer, phone]) or question == "Choisir une question de sécurité":
            print("Erreur : Tous les champs sont obligatoires.")
            return

        # 2. Vérification des mots de passe
        if password != confirm:
            print("Erreur : Les mots de passe ne correspondent pas.")
            return

        # 3. Sécurisation du mot de passe
        hashed_password = generate_password_hash(password)
        
        try:
            self.cursor.execute('''
                INSERT INTO users (fullname, email, phone, password, sec_question, sec_answer)
                VALUES (?, ?, ?, ?, ?, ?)
            ''', (fullname, email, phone, hashed_password, question, answer.lower().strip()))
            self.conn.commit()
            print("Inscription réussie !")
            self.root.current = 'login' # Redirection après succès
        except sqlite3.IntegrityError:
            print("Erreur : Cet email est déjà utilisé.")
        except Exception as e:
            print(f"Erreur inconnue : {e}")

    def find_account_for_recovery(self, identifier):
        """Recherche automatique du compte par Email ou Numéro."""
        if not identifier:
            return

        # Ici, tu feras une requête au serveur : requests.get(f"{SERVER_URL}/get_question/{identifier}")
        # Simulation d'une réponse serveur :
        user_question = "Le nom de votre école primaire ?" # Récupéré du serveur
        
        recovery_screen = self.root.get_screen('recovery')
        recovery_screen.ids.display_question.text = user_question
        recovery_screen.ids.step2.opacity = 1 # On affiche la suite

    def build(self):
        self.theme_cls.primary_palette = "Indigo"
        self.theme_cls.accent_palette = "Amber"
        self.theme_cls.theme_style = "Light"
        return Builder.load_file("main.kv")

    def process_login(self, email_or_phone, password):
        """Vérifie les identifiants et connecte l'utilisateur."""
        from kivymd.app import MDApp
        if not email_or_phone or not password:
            print("Veuillez remplir tous les champs.")
            return

        try:
            # On cherche par email OU par téléphone
            self.cursor.execute("SELECT fullname, password, phone FROM users WHERE email=? OR phone=?", 
                                (email_or_phone, email_or_phone))
            user = self.cursor.fetchone()

            if user:
                stored_hashed_password = user[1]
                # Vérification sécurisée du mot de passe
                if check_password_hash(stored_hashed_password, password):
                    # --- AJOUT CRUCIAL ICI ---
                    app = MDApp.get_running_app()
                    # On stocke le téléphone (index 2 de notre requête SELECT)
                    app.phone_utilisateur = user[2] 
                    
                    # Succès ! On passe les données au Dashboard
                    dashboard = self.root.get_screen('dashboard')
                    dashboard.user_fullname = user[0]
                    dashboard.user_balance = "Chargement..." 
                    
                    self.root.current = 'dashboard'
                    print(f"Bienvenue {user[0]} (Tel: {app.phone_utilisateur})")
                else:
                    print("Mot de passe incorrect.")
            else:
                print("Utilisateur non trouvé.")
        except Exception as e:
            print(f"Erreur lors de la connexion : {e}")

    def show_history(self):
        self.root.current = 'history'
        self.fetch_history()

    def fetch_history(self):
        # Simulation d'historique
        print("Récupération des données...")

    def go_back(self):
        self.root.current = 'dashboard'

    # --- BLOC 1 : GESTION DES QUESTIONS DE SÉCURITÉ ---
    def open_question_menu(self, button):
        menu_items = [
            {
                "viewclass": "OneLineListItem",
                "text": q,
                "on_release": lambda x=q: self.set_question(x),
            } for q in self.security_questions
        ]
        self.menu = MDDropdownMenu(caller=button, items=menu_items, width_mult=4)
        self.menu.open()

    def set_question(self, question_text):
        self.root.get_screen('register').ids.question_spinner.text = question_text
        self.menu.dismiss()

    # --- BLOC 2 : GESTION DU PAYS ET INDICATIF ---
    def open_country_menu(self, button):
        """Ouvre le menu des pays basé sur les constantes."""
        menu_items = [
            {
                "viewclass": "OneLineListItem",
                "text": f"{pays} ({data['code']})",
                "on_release": lambda x=pays, y=data['code']: self.set_country(x, y),
            } for pays, data in OPERATEURS_PAR_PAYS.items()
        ]
        self.country_menu = MDDropdownMenu(caller=button, items=menu_items, width_mult=4)
        self.country_menu.open()

    def set_country(self, country_name, country_code):
        """Met à jour l'indicatif visuel sur l'écran d'inscription."""
        self.root.get_screen('register').ids.country_select.text = country_code
        self.country_menu.dismiss()
        print(f"Pays défini : {country_name}")
    
    def show_history(self):
        """Navigue vers l'écran d'historique (à créer)"""
        self.root.current = 'history'

    def open_group_chat(self, group_name):
        """Fonction pour ouvrir la page de discussion du groupe"""
        print(f"Ouverture de la discussion pour : {group_name}")
        # Nous créerons l'écran ChatScreen à l'étape suivante
        # self.root.current = 'chat'
    
    # À ajouter dans la classe LuckyTontineApp dans main.py

    def open_create_group_modal(self):
        """Change d'écran pour aller vers la création de groupe"""
        print("Navigation vers l'écran de création...")
        self.root.current = 'create_group'
    
    def open_group_chat(self, group_name):
        """
        Cette fonction fait le lien entre le clic sur le Dashboard 
        et l'ouverture réelle du groupe de discussion.
        """
        # 1. On récupère l'instance de l'écran de groupe
        group_screen = self.root.get_screen('group_screen')
        
        # 2. On injecte les données (l'ID devrait venir de ta base de données)
        # Pour l'instant, on utilise le nom comme ID de test
        group_screen.tontine_id = "TEST_ID_123" 
        group_screen.group_name = group_name
        
        # 3. On change d'écran
        self.root.current = 'group_screen'

    def manage_auction(self, group_name):
        """Logique pour le parrain ou l'enchérisseur."""
        print(f"Gestion de l'enchère pour : {group_name}")

    def open_support(self):
        """Ouvre l'assistance (WhatsApp ou Email)."""
        import webbrowser
        # Exemple : redirection vers un lien d'assistance
        webbrowser.open("https://wa.me/22890000000") 

    def logout(self):
        """Déconnecte l'utilisateur et revient à l'écran de connexion."""
        self.root.current = 'login'

    def send_to_server(self, endpoint, data, callback):
        """
        Méthode robuste pour communiquer avec le serveur lonatoapi.
        endpoint: la fonction visée (ex: '/tirage' ou '/enchere')
        data: les données à envoyer (dict)
        callback: la fonction à appeler quand le serveur répond
        """
        from kivy.network.urlrequest import UrlRequest
        import json

        params = json.dumps(data)
        headers = {'Content-type': 'application/json', 'Accept': 'text/plain'}
        
        # On utilise UrlRequest car il ne bloque pas l'interface (pas de gel d'écran)
        UrlRequest(
            f"{SERVER_URL}{endpoint}",
            on_success=callback,
            on_failure=self.on_server_error,
            on_error=self.on_server_error,
            req_body=params,
            req_headers=headers
        )

    def on_server_error(self, req, error):
        """Gestionnaire d'erreurs réseau pour éviter le crash de l'APK"""
        print(f"Erreur de connexion serveur : {error}")
        # Ici on pourra ajouter une popup "Problème de connexion"

    def show_toast(self, text, color="info"):
        from kivymd.uix.snackbar import Snackbar
        from kivymd.uix.label import MDLabel
        from kivy.utils import get_color_from_hex
        from kivy.metrics import dp

        # Couleurs personnalisées
        colors = {
            "success": "#2E7D32",
            "error": "#D32F2F",
            "info": "#1976D2"
        }

        # 1. Création de la Snackbar (SANS l'argument text)
        self.snackbar = Snackbar(
            bg_color=get_color_from_hex(colors.get(color, "#333333")),
            duration=3,
        )

        # 2. Ajout du texte via un MDLabel à l'intérieur
        self.snackbar.add_widget(
            MDLabel(
                text=text,
                theme_text_color="Custom",
                text_color=(1, 1, 1, 1), # Blanc
                padding=[dp(20), dp(10)]
            )
        )

        # 3. Affichage
        self.snackbar.open()

if __name__ == "__main__":
    LuckyTontineApp().run()