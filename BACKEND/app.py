import os
import time
import secrets
import random
import hashlib
from flask import Flask, request, jsonify
from flask_sqlalchemy import SQLAlchemy
from werkzeug.utils import secure_filename

app = Flask(__name__)

# --- CONFIGURATION DES DOSSIERS ---
# On utilise un chemin absolu pour PythonAnywhere
basedir = os.path.abspath(os.path.dirname(__file__))
UPLOAD_FOLDER = os.path.join(basedir, 'group_voice_msgs')
if not os.path.exists(UPLOAD_FOLDER):
    os.makedirs(UPLOAD_FOLDER)

# --- CONFIGURATION DE LA BASE DE DONNÉES ---
app.config['SQLALCHEMY_DATABASE_URI'] = 'sqlite:///' + os.path.join(basedir, 'lucky_tontine.db')
app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False
db = SQLAlchemy(app)

API_KEY = "LUCKY_SECRET_99228_XYZ"

# --- DANS app.py (Backend) ---

def check_auth(req):
    """
    Vérification universelle et robuste.
    """
    # Récupère la clé peu importe le nom du header envoyé par l'APK
    received_key = req.headers.get("Authorization") or req.headers.get("X-API-KEY")
    
    # Vérification stricte contre la variable API_KEY définie sur ton serveur
    return received_key == API_KEY

# --- MODÈLES DE DONNÉES (CLASSES) ---
class User(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    phone = db.Column(db.String(20), unique=True, nullable=False)
    fullname = db.Column(db.String(100))
    balance = db.Column(db.Integer, default=0)
    luck_points = db.Column(db.Integer, default=0)

class TontineGroup(db.Model):
    id = db.Column(db.String(20), primary_key=True)
    name = db.Column(db.String(100), nullable=False)
    amount = db.Column(db.Integer, nullable=False)
    frequency = db.Column(db.String(50))
    invite_code = db.Column(db.String(10), unique=True)
    parrain_phone = db.Column(db.String(20))
    max_members = db.Column(db.Integer, default=10)
    current_members = db.Column(db.Integer, default=1)
    statut = db.Column(db.String(20), default="OPEN")
    participants = db.Column(db.Text) # Liste stockée en texte (ex: "phone1,phone2")

class ChatMessage(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    tontine_id = db.Column(db.String(20), nullable=False)
    msg_id = db.Column(db.String(50), unique=True)
    sender = db.Column(db.String(20))
    filename = db.Column(db.String(100))
    timestamp = db.Column(db.String(20))
    seen_by = db.Column(db.Text, default="") 
    is_deleted = db.Column(db.Boolean, default=False) # Nouveau : pour la suppression
    edit_count = db.Column(db.Integer, default=0)    # Nouveau : pour la modification

class Transaction(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    user_id = db.Column(db.Integer, db.ForeignKey('user.id'), nullable=True) # None pour le Pot Chance
    type_mouvement = db.Column(db.String(50)) # ex: 'AUCTION_GAIN'
    montant = db.Column(db.Float)
    solde_avant = db.Column(db.Float)
    solde_apres = db.Column(db.Float)
    reference_id = db.Column(db.String(100)) # ex: 'AUC-5'
    timestamp = db.Column(db.DateTime, default=db.func.now())

# Création de la base de données au démarrage
with app.app_context():
    db.create_all()

# --- INITIALISATION DE LA BASE DE DONNÉES ET DONNÉES DE TEST ---
with app.app_context():
    db.create_all()
    
    # Vérifier si ton compte de test existe, sinon le créer en base réelle
    admin_user = User.query.filter_by(phone="96110013").first()
    if not admin_user:
        new_admin = User(
            phone="96110013",
            fullname="SOSSOU Komlan Dérick",
            balance=100000,
            luck_points=50
        )
        db.session.add(new_admin)
        
    # Compte de test secondaire
    test_user = User.query.filter_by(phone="22890000000").first()
    if not test_user:
        new_test = User(
            phone="22890000000",
            fullname="Compte de Test",
            balance=5000,
            luck_points=0
        )
        db.session.add(new_test)
        
    db.session.commit()
    print("Base de données initialisée avec les comptes de test.")


def generate_secure_invite_code(user_phone):
    """
    Génère un code de parrainage unique, lié au téléphone de l'utilisateur,
    mais rendu imprévisible par un hashage.
    """
    # On crée une signature unique basée sur le téléphone + le temps
    raw_sig = f"{user_phone}{time.time()}{secrets.token_hex(4)}"
    hash_sig = hashlib.sha256(raw_sig.encode()).hexdigest().upper()
    
    # Format : PAR - (4 premiers chiffres du tel) - (3 caractères du hash)
    # Exemple: PAR-9611-A4F
    prefix = user_phone[:4]
    suffix = hash_sig[:3]
    return f"PAR-{prefix}-{suffix}"

# Utilisation dans create_tontine :
# code = generate_secure_invite_code(data.get('parrain_phone'))

@app.route('/')
def security_check():
    # Si quelqu'un tape l'URL dans un navigateur, il ne voit rien d'utile
    return "<h1>Status: Active</h1>", 200

@app.route('/api/get_user_info', methods=['POST'])
def get_user_info():
    if not check_auth(request): return jsonify({"error": "403"}), 403
    
    data = request.json
    phone = data.get('phone')
    
    # On cherche l'utilisateur dans la base de données réelle
    user = User.query.filter_by(phone=phone).first()
    
    if user:
        return jsonify({
            "fullname": user.fullname,
            "balance": user.balance,
            "luck_points": user.luck_points,
            "announcement": "Connexion sécurisée."
        }), 200
    else:
        # Si c'est un nouvel utilisateur (ex: première connexion)
        # On peut le créer automatiquement pour ne pas bloquer l'APK
        try:
            new_user = User(phone=phone, fullname="Nouvel Utilisateur", balance=0)
            db.session.add(new_user)
            db.session.commit()
            return jsonify({
                "fullname": new_user.fullname,
                "balance": 0,
                "luck_points": 0,
                "announcement": "Bienvenue sur Lucky Tontine !"
            }), 200
        except:
            return jsonify({"error": "Erreur création utilisateur"}), 500

def get_current_time():
    """Retourne l'heure format WhatsApp (ex: 14:30)"""
    return time.strftime("%H:%M", time.localtime())

@app.route('/api/create_tontine', methods=['POST'])
def create_tontine():
    if not check_auth(request): return jsonify({"error": "403"}), 403
    data = request.json
    
    try:
        # Génération d'un code unique de parrainage
        new_invite_code = secrets.token_hex(3).upper()
        t_id = f"T-{random.randint(1000, 9999)}"
        
        new_group = TontineGroup(
            id=t_id,
            name=data.get('group_name'),
            amount=int(data.get('amount')),
            frequency=data.get('frequency', 'Mensuel'),
            invite_code=new_invite_code,
            parrain_phone=data.get('parrain_phone'),
            participants=data.get('parrain_phone'), # Le parrain est le premier
            max_members=int(data.get('max_members', 10))
        )
        
        db.session.add(new_group)
        db.session.commit()
        
        return jsonify({
            "status": "success",
            "invite_code": new_invite_code,
            "tontine_id": t_id
        }), 200
    except Exception as e:
        return jsonify({"error": str(e)}), 500
    
@app.route('/api/get_available_tontines', methods=['GET'])
def get_available_tontines():
    if not check_auth(request): return jsonify({"error": "403"}), 403
    
    groups = TontineGroup.query.filter_by(statut="OPEN").all()
    output = []
    for g in groups:
        # On ne prend que les groupes non complets
        if g.current_members < g.max_members:
            output.append({
                "id": g.id,
                "name": g.name,
                "amount": g.amount,
                "current_members": g.current_members,
                "max_members": g.max_members,
                "invite_code": g.invite_code
            })
    return jsonify({"tontines": output}), 200

@app.route('/api/join_by_code', methods=['POST'])
def join_by_code():
    if not check_auth(request): return jsonify({"error": "403"}), 403
    data = request.json
    code = data.get('invite_code')
    phone = data.get('phone')

    group = TontineGroup.query.filter_by(invite_code=code).first()
    
    if not group:
        return jsonify({"error": "Code invalide"}), 404
    
    if phone in group.participants.split(','):
        return jsonify({"error": "Déjà membre"}), 400
        
    if group.current_members >= group.max_members:
        return jsonify({"error": "Groupe plein"}), 400

    # Mise à jour réelle en base de données
    group.participants += f",{phone}"
    group.current_members += 1
    db.session.commit()

    return jsonify({"status": "success", "group_name": group.name}), 200

# --- FONCTION DE DISTRIBUTION FINANCIÈRE (ALGORITHME LUCKY TONTINE) ---
@app.route('/api/distribute_auction', methods=['POST'])
def distribute_auction():
    """
    ALGORITHME LUCKY TONTINE - VERSION FINALE
    Distribution des parts (Admin, Parrain, Pot Chance, Membres),
    mise à jour des soldes et enregistrement des transactions spécifiques à l'enchère.
    """
    if not check_auth(request):
        return jsonify({"error": "403"}), 403

    data = request.json
    t_id = data.get('tontine_id')
    enchere_reelle = float(data.get('enchere', 0))
    winner_phone = data.get('winner_phone')
    requesting_phone = data.get('phone')

    # 1. RÉCUPÉRATION DE LA TONTINE
    tontine = TontineGroup.query.get(t_id)
    if not tontine:
        return jsonify({"error": "Groupe introuvable"}), 404
    
    # SÉCURITÉ : Seul le parrain peut déclencher
    if requesting_phone != tontine.parrain_phone:
        return jsonify({"error": "Action réservée au parrain"}), 403

    try:
        # 2. CALCULS
        participants_list = tontine.participants.split(',')
        N = len(participants_list)
        mise_minimale = tontine.amount
        
        # Bouclier anti-fraude
        ec = max(enchere_reelle, mise_minimale * 0.5)

        # Répartition des parts
        part_admin = int(ec * 0.15)
        part_parrain_commission = int(ec * 0.10)
        montant_pot_chance = int(ec * 0.05)
        rp = ec * 0.70
        nb_beneficiaires = N - 1
        dividende = int(rp // nb_beneficiaires)

        # 3. DISTRIBUTION ET LOGS
        pot_total = mise_minimale * N
        reference_id = f"AUC-{tontine.id}"  # 🔹 Référence unique pour tracer l'enchère

        # --- A. Gagnant ---
        gagnant = User.query.filter_by(phone=winner_phone).first()
        if gagnant:
            gain_net = pot_total - int(enchere_reelle)
            solde_avant = gagnant.balance
            gagnant.balance += gain_net
            # 🔹 AJOUT LOG TRANSACTION spécifique à l'enchère
            nouvelle_transac = Transaction(
                user_id=gagnant.id,
                type_mouvement='AUCTION_GAIN',  # 🔹 Type unique
                montant=gain_net,
                solde_avant=solde_avant,
                solde_apres=gagnant.balance,
                reference_id=reference_id
            )
            db.session.add(nouvelle_transac)

        # --- B. Parrain ---
        parrain = User.query.filter_by(phone=tontine.parrain_phone).first()
        if parrain:
            gain_parrain = part_parrain_commission + dividende
            solde_avant = parrain.balance
            parrain.balance += gain_parrain
            # 🔹 AJOUT LOG TRANSACTION spécifique à l'enchère
            nouvelle_transac = Transaction(
                user_id=parrain.id,
                type_mouvement='AUCTION_COMMISSION',  # 🔹 Type unique
                montant=gain_parrain,
                solde_avant=solde_avant,
                solde_apres=parrain.balance,
                reference_id=reference_id
            )
            db.session.add(nouvelle_transac)

        # --- C. Membres ---
        for p_phone in participants_list:
            if p_phone != winner_phone and p_phone != tontine.parrain_phone:
                membre = User.query.filter_by(phone=p_phone).first()
                if membre:
                    solde_avant = membre.balance
                    membre.balance += dividende
                    # 🔹 AJOUT LOG TRANSACTION spécifique à l'enchère
                    nouvelle_transac = Transaction(
                        user_id=membre.id,
                        type_mouvement='AUCTION_DIVIDENDE',  # 🔹 Type unique
                        montant=dividende,
                        solde_avant=solde_avant,
                        solde_apres=membre.balance,
                        reference_id=reference_id
                    )
                    db.session.add(nouvelle_transac)

        # --- D. Pot Chance ---
        # 🔹 Enregistrement administratif pour audit, pas lié à un utilisateur
        nouvelle_transac = Transaction(
            user_id=None,
            type_mouvement='AUCTION_POT_CHANCE',  # 🔹 Type unique
            montant=montant_pot_chance,
            solde_avant=0,
            solde_apres=0,
            reference_id=reference_id
        )
        db.session.add(nouvelle_transac)

        # 4. FINALISATION
        tontine.statut = "AUCTION_FINISHED"
        db.session.commit()

        return jsonify({
            "status": "success",
            "message": "Distribution algorithmique effectuée",
            "details": {
                "dividende_membre": dividende,
                "part_admin_bloquee": part_admin,
                "pot_chance": montant_pot_chance
            }
        }), 200

    except Exception as e:
        db.session.rollback()
        return jsonify({"error": f"Erreur critique algo : {str(e)}"}), 500


# --- ROUTE POUR LE TIRAGE DU POT DE CHANCE ---
@app.route('/api/trigger_pot_chance', methods=['POST'])
def pot_chance_api():
    if not check_auth(request):
        return jsonify({"error": "Unauthorized"}), 403
        
    data = request.json
    t_id = data.get('tontine_id')
    
    # Recherche SQL de la tontine
    tontine = TontineGroup.query.get(t_id)

    if not tontine or tontine.pot_chance_temp <= 0:
        return jsonify({"error": "Aucun pot de chance disponible pour ce groupe"}), 400

    try:
        # Sélection du gagnant parmi les participants inscrits
        participants_list = tontine.participants.split(',')
        winner_phone = random.choice(participants_list)
        montant = tontine.pot_chance_temp

        # Mise à jour SQL du solde du gagnant
        user_winner = User.query.filter_by(phone=winner_phone).first()
        if user_winner:
            user_winner.balance += int(montant)
            
            # Archivage et clôture
            tontine.pot_chance_temp = 0
            tontine.statut = "CLOSED"
            # On stocke le nom pour l'affichage final
            tontine.name = f"{tontine.name} (Gagnant Pot: {user_winner.fullname})"
            
            db.session.commit()

            return jsonify({
                "status": "success",
                "winner": user_winner.fullname,
                "amount": montant
            }), 200
        return jsonify({"error": "Gagnant introuvable en base"}), 404

    except Exception as e:
        db.session.rollback()
        return jsonify({"error": f"Erreur tirage: {str(e)}"}), 500

@app.route('/api/participate_tontine', methods=['POST'])
def participate_tontine():
    if not check_auth(request):
        return jsonify({"error": "Unauthorized"}), 403
        
    data = request.json
    phone = data.get('phone')
    t_id = data.get('tontine_id')

    # Récupération des entités SQL
    user = User.query.filter_by(phone=phone).first()
    tontine = TontineGroup.query.get(t_id)

    if not user or not tontine:
        return jsonify({"status": "error", "message": "Utilisateur ou Groupe inexistant"}), 400

    # --- LOGIQUE DE SÉCURITÉ ---
    if tontine.statut != "OPEN":
        return jsonify({"status": "error", "message": "Cette tontine ne prend plus d'inscriptions"}), 400

    if user.balance < tontine.amount:
        return jsonify({"status": "error", "message": "Solde insuffisant pour la mise"}), 400

    participants_list = tontine.participants.split(',')
    if phone in participants_list:
        return jsonify({"status": "error", "message": "Déjà inscrit"}), 400

    try:
        # --- EXÉCUTION ---
        user.balance -= tontine.amount
        tontine.participants += f",{phone}"
        tontine.current_members += 1

        resultat = {"action": "registered", "new_balance": user.balance}
        
        # Tirage automatique si le groupe est plein
        if tontine.current_members >= tontine.max_members:
            new_list = tontine.participants.split(',')
            gagnant_phone = random.choice(new_list)
            
            total_pot = tontine.amount * tontine.max_members
            gain_net = int(total_pot * 0.90) # 10% frais admin inclus
            
            winner_user = User.query.filter_by(phone=gagnant_phone).first()
            if winner_user:
                winner_user.balance += gain_net
                tontine.statut = "CLOSED"
                
                resultat["action"] = "tontine_completed"
                resultat["winner"] = winner_user.fullname
                resultat["gain"] = gain_net

        db.session.commit()
        return jsonify(resultat), 200

    except Exception as e:
        db.session.rollback()
        return jsonify({"error": str(e)}), 500

@app.route('/admin/dashboard/ADMIN_LUCKY_2026_SECURE')
def admin_view():
    # Note: Dans une URL, on évite les <secret_key> variables pour l'admin
    # pour ne pas qu'elles soient loggées par le navigateur.
    
    users = User.query.all()
    tontines = TontineGroup.query.all()

    html = "<h1>TABLEAU DE BORD ADMIN - BASE RÉELLE</h1>"
    html += f"<h2>Utilisateurs ({len(users)})</h2><ul>"
    for u in users:
        html += f"<li><b>{u.fullname}</b> ({u.phone}) : {u.balance} FCFA</li>"
    
    html += "</ul><h2>Groupes Actifs</h2>"
    for t in tontines:
        progression = (t.current_members / t.max_members) * 100
        html += f"<p>ID: {t.id} | {t.name} : {t.current_members}/{t.max_members} ({progression}%) - Statut: {t.statut}</p>"
    
    return html, 200

@app.route('/api/send_voice', methods=['POST'])
def send_voice():
    """Version Professionnelle : Sécurisée contre l'écrasement de fichiers et les pannes SQL"""
    if not check_auth(request): return jsonify({"error": "403"}), 403
    
    # Sécurité supplémentaire : On s'assure que le dossier existe avant d'écrire
    if not os.path.exists(UPLOAD_FOLDER):
        os.makedirs(UPLOAD_FOLDER)

    if 'voice' not in request.files:
        return jsonify({"error": "Aucun fichier audio"}), 400
    
    voice_file = request.files['voice']
    t_id = request.form.get('tontine_id')
    sender = request.form.get('sender_phone')

    if voice_file and t_id and sender:
        try:
            # 1. Sécurisation du nom de fichier (Anti-Hacking)
            ts = int(time.time())
            # On crée un ID de message unique et imprévisible
            unique_msg_id = f"MSG_{ts}_{secrets.token_hex(4)}"
            
            # secure_filename nettoie les caractères dangereux
            clean_name = secure_filename(f"{t_id}_{ts}_{sender}.3gp")
            file_path = os.path.join(UPLOAD_FOLDER, clean_name)
            
            # Sauvegarde physique
            voice_file.save(file_path)

            # 2. Enregistrement SQL atomique
            new_msg = ChatMessage(
                tontine_id=t_id,
                msg_id=unique_msg_id,
                sender=sender,
                filename=clean_name,
                timestamp=time.strftime("%H:%M"),
                seen_by=sender # L'envoyeur est le premier lecteur
            )
            db.session.add(new_msg)
            db.session.commit()

            return jsonify({
                "status": "success", 
                "file_url": clean_name,
                "msg_id": unique_msg_id
            }), 200

        except Exception as e:
            # En cas d'erreur, on annule la transaction SQL pour éviter les données corrompues
            db.session.rollback()
            return jsonify({"error": f"Échec de l'envoi : {str(e)}"}), 500
            
    return jsonify({"error": "Données incomplètes"}), 400

@app.route('/api/get_voices/<t_id>', methods=['GET'])
def get_voices(t_id):
    if not check_auth(request): return jsonify({"error": "403"}), 403
    
    try:
        messages = ChatMessage.query.filter_by(tontine_id=t_id).order_by(ChatMessage.id.desc()).all()
        output = []
        for m in messages:
            output.append({
                "msg_id": m.msg_id,
                "sender": m.sender,
                "filename": m.filename,
                "time": m.timestamp,
                "seen_by": m.seen_by.split(',') if m.seen_by else []
            })
        return jsonify({"voices": output}), 200
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/api/mark_as_seen', methods=['POST'])
def mark_as_seen():
    if not check_auth(request): return jsonify({"error": "403"}), 403
    
    data = request.json
    msg_id = data.get('msg_id')
    viewer_phone = data.get('phone')

    msg = ChatMessage.query.filter_by(msg_id=msg_id).first()
    if msg:
        current_viewers = msg.seen_by.split(',') if msg.seen_by else []
        if viewer_phone not in current_viewers:
            current_viewers.append(viewer_phone)
            msg.seen_by = ",".join(current_viewers)
            db.session.commit()
        
        return jsonify({"status": "updated", "seen_count": len(current_viewers)}), 200
                
    return jsonify({"error": "Message non trouvé"}), 404

@app.route('/api/delete_message', methods=['POST'])
def delete_message():
    if not check_auth(request): return jsonify({"error": "403"}), 403
    
    data = request.json
    msg_id = data.get('msg_id')
    user_phone = data.get('phone')

    msg = ChatMessage.query.filter_by(msg_id=msg_id).first()
    
    if not msg:
        return jsonify({"error": "Message introuvable"}), 404
        
    # SÉCURITÉ : Seul celui qui a envoyé le message peut le supprimer
    if msg.sender != user_phone:
        return jsonify({"error": "Action non autorisée"}), 403

    try:
        msg.is_deleted = True
        # Optionnel : Supprimer le fichier physique pour gagner de la place
        # file_path = os.path.join(UPLOAD_FOLDER, msg.filename)
        # if os.path.exists(file_path): os.remove(file_path)
        
        db.session.commit()
        return jsonify({"status": "success", "message": "Message supprimé"}), 200
    except Exception as e:
        db.session.rollback()
        return jsonify({"error": str(e)}), 500
    
# --- LANCEMENT DU SERVEUR ---
if __name__ == '__main__':
    with app.app_context():
        # Cette ligne crée le fichier lucky_tontine.db et toutes les tables (User, Transaction, etc.)
        db.create_all()
        print("-------------------------------------------------------")
        print("Base de données initialisée avec succès !")
        print("Fichier 'lucky_tontine.db' prêt sur le disque.")
        print("-------------------------------------------------------")
    
    # Lancement local (VS Code)
    # Sur PythonAnywhere, ce bloc ne sera pas utilisé, c'est l'onglet 'Web' qui gère le démarrage
    app.run(debug=True, host='0.0.0.0', port=5000)