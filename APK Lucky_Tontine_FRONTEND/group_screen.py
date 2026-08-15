import requests
import threading
import time
import os
from kivy.utils import platform
from kivy.clock import Clock
from kivy.properties import StringProperty, BooleanProperty
from kivymd.uix.screen import MDScreen
from kivymd.uix.boxlayout import MDBoxLayout
from kivymd.toast import toast
from kivy.app import App

# --- CONFIGURATION GLOBALE ---
SERVER_URL = "https://tontineserveur.pythonanywhere.com" # Remplace par ton vrai lien
API_KEY = "LUCKY_SECRET_99228_XYZ"

# --- LE WIDGET POUR LES MESSAGES (Indispensable pour corriger l'erreur) ---
class VoiceMessageWidget(MDBoxLayout):
    is_me = BooleanProperty(False)
    send_time = StringProperty("")
    is_fully_seen = BooleanProperty(False)
    
    def play_audio(self):
        # Logique pour télécharger et jouer le son
        toast("Lecture du message...")

class GroupScreen(MDScreen):
    group_name = StringProperty("Chargement...")
    pot_total = StringProperty("0")
    tontine_id = StringProperty("")
    current_group_participants = [] # Liste pour compter les membres

    def on_enter(self):
        """Appelé à l'ouverture de la page"""
        self.load_group_details()

    def load_group_details(self):
        """
        Récupère les détails du groupe de manière robuste.
        """
        import threading
        import requests
        from kivy.app import App
        from kivy.clock import Clock
        
        # 1. RÉCUPÉRATION DU TÉLÉPHONE (Source de l'erreur)
        app = App.get_running_app()
        user_phone = getattr(app, 'phone_utilisateur', getattr(app, 'user_phone', None))

        if not user_phone:
            print("ERREUR : Impossible de charger les détails (téléphone manquant).")
            return

        # 2. LOGIQUE DE RÉCUPÉRATION (On passe l'argument explicitement)
        def fetch_logic(phone_to_use):
            try:
                headers = {
                    "Authorization": API_KEY,
                    "X-API-KEY": API_KEY,
                    "Content-Type": "application/json"
                }
                
                # On utilise 'phone_to_use' au lieu de 'phone' pour éviter les conflits
                response = requests.post(
                    f"{SERVER_URL}/api/get_user_info",
                    json={"phone": phone_to_use},
                    headers=headers,
                    timeout=10
                )
                
                if response.status_code == 200:
                    data = response.json()
                    # Mise à jour de l'interface sur le thread principal
                    Clock.schedule_once(lambda dt: self.apply_data_update(data))
                elif response.status_code == 403:
                    print("ERREUR 403 : Problème de clé API ou de Header.")
                elif response.status_code == 404:
                    print(f"ERREUR : Le numéro {phone_to_use} n'existe pas.")
                else:
                    print(f"ERREUR SERVEUR : Code {response.status_code}")
                    
            except Exception as e:
                print(f"ERREUR RÉSEAU CRITIQUE : {e}")

        # 3. LANCEMENT DU THREAD (On injecte user_phone dans args)
        threading.Thread(target=fetch_logic, args=(user_phone,), daemon=True).start()

    def apply_group_data(self, data):
        """Applique les données reçues sur l'écran"""
        app = App.get_running_app()
        self.group_name = data.get('name', 'Sans nom')
        self.pot_total = str(data.get('pot_total', 0))
        self.current_group_participants = data.get('participants', [])
        
        # Vérification si l'utilisateur est le parrain (pour afficher les boutons admin)
        is_parrain = (app.user_phone == data.get('parrain_phone'))
        self.ids.admin_layout.opacity = 1 if is_parrain else 0
        self.ids.admin_layout.disabled = not is_parrain

    def submit_auction_result(self, amount):
        # Ici on insère la fonction de distribution que nous avons créée avant
        # Elle enverra 'amount' et 'self.selected_winner_phone' au serveur
        pass

    def start_voice_record(self):
        """Déclenche l'enregistrement (Android uniquement)"""
        if platform == 'android':
            try:
                from android.media import MediaRecorder
                self.recorder = MediaRecorder()
                self.recorder.setAudioSource(1) # MIC
                self.recorder.setOutputFormat(3) # THREE_GPP
                self.recorder.setAudioEncoder(1) # AMR_NB
                # Chemin privé pour l'app pour éviter les erreurs de permission
                from android.storage import app_storage_details
                self.output_path = f"{app_storage_details().files_path}/vocal_temp.3gp"
                
                self.recorder.setOutputFile(self.output_path)
                self.recorder.prepare()
                self.recorder.start()
            except Exception as e:
                print(f"Erreur Record: {e}")
        else:
            print("Micro activé (Simulation PC)")

    def stop_and_send_voice(self):
        """Arrête et déclenche l'envoi réel"""
        if platform == 'android':
            try:
                self.recorder.stop()
                self.recorder.release()
                self.recorder = None
                # APPEL DE LA FONCTION RÉELLE D'ENVOI
                self.upload_voice_server(self.output_path)
            except Exception as e:
                print(f"Erreur Stop: {e}")
        else:
            print("Enregistrement arrêté (PC)")

    def upload_voice_server(self, file_path):
        """ENVOI RÉEL DU FICHIER AU SERVEUR FLASK"""
        def upload_thread():
            app = App.get_running_app()
            try:
                url = f"{SERVER_URL}/api/send_voice"
                # On ouvre le fichier binaire
                with open(file_path, 'rb') as f:
                    files = {'voice': f}
                    data = {
                        'tontine_id': self.tontine_id,
                        'sender_phone': app.user_phone # Ton numéro stocké dans l'app
                    }
                    response = requests.post(url, files=files, data=data, timeout=30)
                
                if response.status_code == 200:
                    Clock.schedule_once(lambda dt: toast("Vocal envoyé"))
                else:
                    Clock.schedule_once(lambda dt: toast("Échec de l'envoi vocal"))
            except Exception as e:
                print(f"Erreur Upload: {e}")

        import threading
        threading.Thread(target=upload_thread, daemon=True).start()
    
    def update_chat_view(self, messages_list):
        """Met à jour l'écran de chat avec les messages et les coches bleues."""
        app = App.get_running_app()
        self.ids.members_list.clear_widgets()
        # On récupère le nombre total de participants pour savoir si c'est "lu par tous"
        total_members = len(self.current_group_participants) 

        for msg in messages_list:
            # RÈGLE : Double coche bleue si tout le monde a vu
            fully_seen = len(msg['seen_by']) >= total_members
            
            # Création du widget visuel (basé sur le .kv que nous avons vu)
            new_vocal = VoiceMessageWidget(
                is_me = (msg['sender'] == app.user_phone),
                send_time = msg['time'],
                is_fully_seen = fully_seen
            )
            
            # Ajout à l'interface
            self.ids.members_list.add_widget(new_vocal)
            
            # LOGIQUE DE LECTURE AUTOMATIQUE
            # Si je vois le message et que je ne suis pas encore dans 'seen_by'
            if app.user_phone not in msg['seen_by']:
                self.notify_server_seen(msg['msg_id'])

    def notify_server_seen(self, msg_id):
        """Informe le serveur que nous avons lu ce message précis."""
        app = App.get_running_app()
        url = f"{SERVER_URL}/api/mark_as_seen"
        payload = {
            "tontine_id": self.tontine_id,
            "msg_id": msg_id,
            "phone": app.user_phone
        }
        
        # Header corrigé : Authorization au lieu de X-API-KEY
        headers = {"Authorization": API_KEY}
        
        def send_request():
            try:
                requests.post(url, json=payload, headers=headers, timeout=5)
            except Exception as e:
                print(f"Erreur notification lecture: {e}")

        threading.Thread(target=send_request, daemon=True).start()