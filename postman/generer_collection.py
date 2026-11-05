"""Génère la collection Postman FitBook et son environnement local.

Usage :  python postman/generer_collection.py
La collection décrit un parcours complet, à exécuter dans l'ordre (Newman) sur une base
chargée avec les fixtures : authentification, recherche, réservation, coach,
gestionnaire, administrateur, et les cas d'erreur (401, 403, 404, 409, 422).
"""
import json
import pathlib
import uuid

ICI = pathlib.Path(__file__).parent


def requete(nom, methode, chemin, corps=None, tests=(), jeton=None, avant=()):
    """Une requête : corps dict (JSON) ou chaîne brute (pour les variables numériques)."""
    url = {"raw": "{{baseUrl}}" + chemin, "host": ["{{baseUrl}}"],
           "path": chemin.split("?")[0].strip("/").split("/")}
    if "?" in chemin:
        url["query"] = [{"key": kv.split("=")[0], "value": kv.split("=", 1)[1]} for kv in chemin.split("?", 1)[1].split("&")]
    r = {"method": methode, "header": [{"key": "Accept", "value": "application/json"}], "url": url,
         "auth": {"type": "bearer", "bearer": [{"key": "token", "value": "{{" + jeton + "}}", "type": "string"}]} if jeton else {"type": "noauth"}}
    if corps is not None:
        r["header"].append({"key": "Content-Type", "value": "application/json"})
        r["body"] = {"mode": "raw", "raw": corps if isinstance(corps, str) else json.dumps(corps, ensure_ascii=False, indent=2)}
    evenements = [{"listen": "test", "script": {"type": "text/javascript", "exec": list(tests)}}]
    if avant:
        evenements.append({"listen": "prerequest", "script": {"type": "text/javascript", "exec": list(avant)}})
    item = {"name": nom, "event": evenements, "request": r}
    if jeton is None:
        # Requête anonyme : sans le cookie BEARER posé par une connexion précédente.
        item["protocolProfileBehavior"] = {"disableCookies": True}
    return item


def statut(*codes):
    return f'pm.test("Statut {" ou ".join(map(str, codes))}", () => pm.expect(pm.response.code).to.be.oneOf({json.dumps(list(codes))}));'


def erreurs(*champs):
    attendus = " ".join(f'pm.expect(e).to.have.property("{c}");' for c in champs)
    return f'pm.test("Erreurs par champ : {", ".join(champs)}", () => {{ const e = pm.response.json().errors; {attendus} }});'


PAGINE = 'pm.test("Réponse paginée", () => { const j = pm.response.json(); ["items", "page", "parPage", "total", "pages"].forEach(k => pm.expect(j).to.have.property(k)); });'


def connexion(nom, email, mdp, variable):
    return requete(nom, "POST", "/api/login", {"email": email, "password": mdp}, [
        statut(200), "const j = pm.response.json();",
        'pm.test("Jeton JWT renvoyé", () => pm.expect(j.token).to.be.a("string"));',
        f'pm.collectionVariables.set("{variable}", j.token);'])


authentification = [
    connexion("Connexion sportif", "{{sportifEmail}}", "Sportif123!", "tokenSportif"),
    requete("Connexion — mauvais mot de passe (401)", "POST", "/api/login", {"email": "{{sportifEmail}}", "password": "faux-mot-de-passe"}, [statut(401)]),
    requete("Connexion — adresse non confirmée (403)", "POST", "/api/login", {"email": "sportif39@fitbook.test", "password": "Sportif123!"}, [statut(403)]),
    requete("Connexion — compte bloqué (403)", "POST", "/api/login", {"email": "sportif40@fitbook.test", "password": "Sportif123!"}, [statut(403)]),
    requete("Utilisateur courant", "GET", "/api/me", None, [
        statut(200), 'pm.test("Rôle sportif", () => pm.expect(pm.response.json().role).to.eql("sportif"));',
        'pm.test("Mot de passe jamais exposé", () => pm.expect(pm.response.json()).to.not.have.property("password"));'], "tokenSportif"),
    requete("Utilisateur courant sans jeton (401)", "GET", "/api/me", None, [statut(401)]),
    requete("Inscription — rôle admin refusé (422)", "POST", "/api/inscription",
            {"prenom": "Eve", "nom": "Pirate", "email": "eve.{{$timestamp}}@exemple.test", "telephone": "0470123456", "password": "MotDePasse123!", "role": "admin"},
            [statut(422), erreurs("role")]),
    requete("Inscription — champs invalides (422)", "POST", "/api/inscription",
            {"prenom": "", "nom": "", "email": "pas-un-email", "telephone": "abc", "password": "court"},
            [statut(422), erreurs("prenom", "email", "password")]),
    requete("Mot de passe oublié — réponse neutre", "POST", "/api/auth/forgot-password", {"email": "inconnu@exemple.test"}, [statut(200)]),
]

catalogue = [
    requete("Activités", "GET", "/api/activites", None, [statut(200), 'pm.test("Liste non vide", () => pm.expect(pm.response.json()).to.be.an("array").that.is.not.empty);']),
    requete("Recherche de séances (yoga)", "GET", "/api/recherche?type=seances&activite=yoga&parPage=5", None, [
        statut(200), PAGINE,
        'pm.test("Uniquement du yoga", () => pm.response.json().items.forEach(s => pm.expect(s.activite.nom.toLowerCase()).to.include("yoga")));']),
    requete("Recherche triée par prix", "GET", "/api/recherche?tri=prix&parPage=20", None, [
        statut(200), 'pm.test("Prix croissants", () => { const p = pm.response.json().items.map(s => +s.prix); pm.expect(p).to.eql([...p].sort((a, b) => a - b)); });']),
    requete("Salles autour de Bruxelles (5 km)", "GET", "/api/recherche?type=salles&lat=50.8466&lng=4.3528&rayon=5&tri=distance", None, [
        statut(200), PAGINE,
        'pm.test("Distances croissantes et ≤ 5 km", () => { const d = pm.response.json().items.map(s => s.distanceKm); d.forEach(x => pm.expect(x).to.be.at.most(5)); pm.expect(d).to.eql([...d].sort((a, b) => a - b)); });',
        'const items = pm.response.json().items; if (items.length) pm.collectionVariables.set("salleId", items[0].id);']),
    requete("Recherche de coachs", "GET", "/api/recherche?type=coachs", None, [
        statut(200), PAGINE, 'const items = pm.response.json().items; if (items.length) pm.collectionVariables.set("coachId", items[0].id);']),
    requete("Recherche — critères invalides (422)", "GET", "/api/recherche?type=planetes&du=2026-13-45&noteMin=9", None, [statut(422), erreurs("type", "du", "noteMin")]),
    requete("Fiche salle", "GET", "/api/salles/{{salleId}}", None, [
        statut(200), 'pm.test("Note, avis, séances, créneaux", () => { const j = pm.response.json(); ["note", "avis", "prochainesSeances", "disponibilites"].forEach(k => pm.expect(j).to.have.property(k)); });']),
    requete("Fiche coach", "GET", "/api/coaches/{{coachId}}", None, [statut(200), 'pm.test("Spécialités", () => pm.expect(pm.response.json().specialites).to.be.an("array"));']),
    requete("Fiche séance introuvable (404)", "GET", "/api/seances/999999", None, [statut(404), 'pm.test("Message JSON", () => pm.expect(pm.response.json().message).to.be.a("string"));']),
]

reservation = [
    requete("Mes réservations à venir", "GET", "/api/reservations?periode=a_venir&parPage=50", None, [
        statut(200), PAGINE, 'pm.collectionVariables.set("dejaReservees", JSON.stringify(pm.response.json().items.map(r => r.seance.id)));'], "tokenSportif"),
    requete("Choix d'une séance réservable (demain ou plus tard)", "GET", "/api/recherche?type=seances&du={{demain}}&parPage=50", None, [
        statut(200),
        'const deja = JSON.parse(pm.collectionVariables.get("dejaReservees") || "[]");',
        'const s = pm.response.json().items.find(x => x.placesDisponibles > 0 && !deja.includes(x.id));',
        'pm.test("Une séance libre existe", () => pm.expect(s).to.not.be.undefined);',
        'pm.collectionVariables.set("seanceId", s.id); pm.collectionVariables.set("placesAvant", s.placesDisponibles);'],
        avant=['pm.collectionVariables.set("demain", new Date(Date.now() + 86400000).toISOString().slice(0, 10));']),
    requete("Réserver — corps refusé par le JSON Schema (422)", "POST", "/api/reservations", {"seanceId": "douze", "nombrePlaces": 3}, [statut(422), erreurs("seanceId", "nombrePlaces")], "tokenSportif"),
    requete("Réserver sans être connecté (401)", "POST", "/api/reservations", {"seanceId": 1}, [statut(401)]),
    requete("Réserver la séance (201)", "POST", "/api/reservations", '{\n  "seanceId": {{seanceId}}\n}', [
        statut(201), "const j = pm.response.json();",
        'pm.test("Réservation confirmée", () => pm.expect(j.statut).to.eql("confirmee"));',
        'pm.test("QR code généré", () => pm.expect(j.qrToken).to.match(/^[0-9a-f]{32}$/));',
        'pm.test("Une place de moins", () => pm.expect(j.seance.placesDisponibles).to.eql(+pm.collectionVariables.get("placesAvant") - 1));',
        'pm.collectionVariables.set("reservationId", j.id);'], "tokenSportif"),
    requete("Réserver deux fois la même séance (409)", "POST", "/api/reservations", '{\n  "seanceId": {{seanceId}}\n}', [statut(409)], "tokenSportif"),
    requete("Détail de ma réservation", "GET", "/api/reservations/{{reservationId}}", None, [statut(200)], "tokenSportif"),
    requete("Réservation lue par un autre sportif (403)", "GET", "/api/reservations/{{reservationId}}", None, [statut(403)], "tokenSportif2"),
    requete("Annuler ma réservation", "POST", "/api/reservations/{{reservationId}}/annuler", None, [
        statut(200, 409), 'if (pm.response.code === 200) pm.test("Statut annulée", () => pm.expect(pm.response.json().statut).to.eql("annulee"));'], "tokenSportif"),
    requete("Annuler une seconde fois (409)", "POST", "/api/reservations/{{reservationId}}/annuler", None, [statut(409)], "tokenSportif"),
    requete("Historique", "GET", "/api/reservations?periode=historique", None, [statut(200), PAGINE], "tokenSportif"),
    requete("Ajouter la séance aux favoris", "POST", "/api/favoris", '{\n  "type": "seance",\n  "cibleId": {{seanceId}}\n}', [
        statut(201, 409), 'if (pm.response.code === 201) pm.collectionVariables.set("favoriId", pm.response.json().id);'], "tokenSportif"),
    requete("Mes favoris", "GET", "/api/favoris", None, [statut(200), 'pm.test("Liste", () => pm.expect(pm.response.json()).to.be.an("array"));'], "tokenSportif"),
    requete("Retirer le favori", "DELETE", "/api/favoris/{{favoriId}}", None, [statut(204, 404)], "tokenSportif"),
    requete("Avis sans participation (403)", "POST", "/api/avis", '{\n  "type": "seance",\n  "cibleId": {{seanceId}},\n  "note": 5\n}', [statut(403)], "tokenSportif"),
    requete("Notifications", "GET", "/api/me/notifications", None, [statut(200), 'pm.test("Compteur de non lues", () => pm.expect(pm.response.json().nonLues).to.be.a("number"));'], "tokenSportif"),
]
# Le second sportif sert au test de propriété (403) : connecté au début du dossier.
reservation.insert(0, connexion("Connexion d'un second sportif", "sportif2@fitbook.test", "Sportif123!", "tokenSportif2"))

SEANCE = {"titre": "Yoga Newman", "activiteId": 1, "debut": "{{debut}}", "fin": "{{fin}}", "capacite": 8, "prix": 12, "niveau": "debutant"}
coach = [
    connexion("Connexion coach", "coach1@fitbook.test", "Coach123!", "tokenCoach"),
    requete("Créer une séance — fin avant début (422)", "POST", "/api/seances", {**SEANCE, "fin": "{{debut}}"}, [statut(422), erreurs("fin")], "tokenCoach", avant=[
        "const d = new Date(Date.now() + 5 * 86400000); d.setUTCHours(17, 0, 0, 0);",
        'pm.collectionVariables.set("debut", d.toISOString());',
        'pm.collectionVariables.set("fin", new Date(d.getTime() + 3600000).toISOString());']),
    requete("Créer une séance (201)", "POST", "/api/seances", SEANCE, [
        statut(201), 'pm.collectionVariables.set("seanceCoachId", pm.response.json().id);',
        'pm.test("Rattachée au coach connecté", () => pm.expect(pm.response.json().coach).to.not.be.null);'], "tokenCoach"),
    requete("Modifier ma séance", "PUT", "/api/seances/{{seanceCoachId}}", {**SEANCE, "titre": "Yoga Newman (modifié)", "capacite": 10}, [
        statut(200), 'pm.test("Capacité 10", () => pm.expect(pm.response.json().capaciteMax).to.eql(10));'], "tokenCoach"),
    requete("Un sportif modifie la séance d'un coach (403)", "PUT", "/api/seances/{{seanceCoachId}}", SEANCE, [statut(403)], "tokenSportif"),
    requete("Inscrits de ma séance", "GET", "/api/seances/{{seanceCoachId}}/inscrits", None, [statut(200), 'pm.test("Liste des inscrits", () => pm.expect(pm.response.json().inscrits).to.be.an("array"));'], "tokenCoach"),
    requete("Mes séances à venir", "GET", "/api/coach/seances?periode=a_venir", None, [statut(200), PAGINE], "tokenCoach"),
    requete("Mes statistiques", "GET", "/api/coach/stats", None, [statut(200), 'pm.test("Évolution sur 12 semaines", () => pm.expect(pm.response.json().evolution).to.have.lengthOf(12));'], "tokenCoach"),
    requete("Annuler ma séance (RG-12)", "POST", "/api/seances/{{seanceCoachId}}/annuler", None, [statut(200), 'pm.test("Séance annulée", () => pm.expect(pm.response.json().statut).to.eql("annulee"));'], "tokenCoach"),
    requete("Réserver une séance annulée (409)", "POST", "/api/reservations", '{\n  "seanceId": {{seanceCoachId}}\n}', [statut(409)], "tokenSportif"),
    requete("Supprimer ma séance", "DELETE", "/api/seances/{{seanceCoachId}}", None, [statut(204)], "tokenCoach"),
]

gestionnaire = [
    connexion("Connexion gestionnaire", "gestionnaire1@fitbook.test", "Gestion123!", "tokenGestion"),
    requete("Mes salles (sp_stats_salle)", "GET", "/api/gestionnaire/salles", None, [
        statut(200), "const s = pm.response.json();",
        'pm.test("Au moins une salle", () => pm.expect(s).to.not.be.empty);',
        'pm.test("Statistiques de la procédure", () => pm.expect(s[0].stats).to.have.property("tauxRemplissage"));',
        'pm.collectionVariables.set("maSalleId", s[0].id);'], "tokenGestion"),
    requete("Statistiques d'une salle", "GET", "/api/salles/{{maSalleId}}/stats", None, [statut(200), 'pm.test("Évolution", () => pm.expect(pm.response.json().evolution).to.have.lengthOf(12));'], "tokenGestion"),
    requete("Statistiques de salle par un sportif (403)", "GET", "/api/salles/{{maSalleId}}/stats", None, [statut(403)], "tokenSportif"),
    requete("Créer une salle — champs invalides (422)", "POST", "/api/salles", {"nom": "", "adresse": "", "ville": "", "codePostal": "abc"}, [statut(422), erreurs("nom", "codePostal")], "tokenGestion"),
    requete("Réservations de mes salles", "GET", "/api/gestionnaire/reservations?periode=a_venir", None, [statut(200), PAGINE], "tokenGestion"),
    requete("Tableau de bord", "GET", "/api/gestionnaire/stats", None, [statut(200), 'pm.test("Par salle", () => pm.expect(pm.response.json().parSalle).to.be.an("array"));'], "tokenGestion"),
]

admin = [
    connexion("Connexion administrateur", "admin@fitbook.test", "Admin123!", "tokenAdmin"),
    requete("Tableau de bord global", "GET", "/api/admin/stats", None, [
        statut(200), 'pm.test("Utilisateurs par rôle", () => pm.expect(pm.response.json().utilisateursParRole).to.have.all.keys("sportif", "coach", "gestionnaire", "admin"));'], "tokenAdmin"),
    requete("Recherche d'utilisateurs", "GET", "/api/admin/utilisateurs?q=sportif&role=sportif", None, [statut(200), PAGINE], "tokenAdmin"),
    requete("Avis non modérés", "GET", "/api/admin/avis?filtre=non_moderes", None, [statut(200), PAGINE], "tokenAdmin"),
    requete("Espace admin ouvert par un sportif (403)", "GET", "/api/admin/stats", None, [statut(403)], "tokenSportif"),
    requete("Activité déjà existante (409)", "POST", "/api/activites", {"nom": "Yoga", "categorie": "Bien-être"}, [statut(409)], "tokenAdmin"),
    requete("Déconnexion", "POST", "/api/logout", None, [statut(204)], "tokenSportif"),
]

collection = {
    "info": {
        "_postman_id": str(uuid.uuid5(uuid.NAMESPACE_URL, "fitbook-collection")),
        "name": "FitBook API",
        "description": "Parcours de test de l'API FitBook (UAA MI08), à exécuter dans l'ordre sur une base chargée avec les fixtures. Voir postman/README.md.",
        "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json",
    },
    "item": [
        {"name": "1. Authentification", "item": authentification},
        {"name": "2. Catalogue et recherche", "item": catalogue},
        {"name": "3. Réservation, favoris, avis (sportif)", "item": reservation},
        {"name": "4. Coach", "item": coach},
        {"name": "5. Gestionnaire", "item": gestionnaire},
        {"name": "6. Administrateur", "item": admin},
    ],
    "variable": [{"key": k, "value": ""} for k in [
        "tokenSportif", "tokenSportif2", "tokenCoach", "tokenGestion", "tokenAdmin", "salleId", "coachId", "seanceId",
        "placesAvant", "reservationId", "favoriId", "dejaReservees", "demain", "debut", "fin", "seanceCoachId", "maSalleId"]],
}
environnement = {
    "id": str(uuid.uuid5(uuid.NAMESPACE_URL, "fitbook-environnement")),
    "name": "FitBook — local",
    "values": [
        {"key": "baseUrl", "value": "http://127.0.0.1:8000", "enabled": True},
        {"key": "sportifEmail", "value": "sportif1@fitbook.test", "enabled": True},
    ],
    "_postman_variable_scope": "environment",
}

(ICI / "fitbook.postman_collection.json").write_text(json.dumps(collection, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
(ICI / "fitbook.postman_environment.json").write_text(json.dumps(environnement, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
print(sum(len(d["item"]) for d in collection["item"]), "requêtes générées")
