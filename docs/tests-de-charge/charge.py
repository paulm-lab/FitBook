"""Test de charge (courbes de stress) de l'API FitBook.

Monte progressivement le nombre d'utilisateurs simultanés sur des routes publiques en lecture
et mesure, pour chaque palier : débit (requêtes/s), temps de réponse moyen, 95e centile et taux
d'erreurs. Produit un CSV et deux courbes SVG, sans dépendance externe (bibliothèque standard).

Usage :
    python docs/tests-de-charge/charge.py                      # API locale, paliers par défaut
    python docs/tests-de-charge/charge.py --url http://127.0.0.1:8000 --paliers 1,5,10,20 --duree 10

À lancer sur l'environnement de développement (jamais sur la production sans accord) :
le serveur local de Symfony traite peu de requêtes en parallèle, les chiffres servent à
repérer le point de saturation, pas à dimensionner la production.
"""

from __future__ import annotations

import argparse
import csv
import statistics
import threading
import time
import urllib.error
import urllib.request
from datetime import datetime
from pathlib import Path

ROUTES = [
    "/api/recherche?type=seances&page=1",
    "/api/activites",
    "/api/salles?page=1",
    "/api/coaches?page=1",
]


def mesurer(url: str, utilisateurs: int, duree: float) -> dict:
    """Fait tourner `utilisateurs` boucles simultanées pendant `duree` secondes."""
    temps: list[float] = []
    erreurs = 0
    verrou = threading.Lock()
    fin = time.perf_counter() + duree

    def utilisateur(numero: int) -> None:
        nonlocal erreurs
        i = numero
        while time.perf_counter() < fin:
            route = ROUTES[i % len(ROUTES)]
            i += 1
            debut = time.perf_counter()
            try:
                requete = urllib.request.Request(url + route, headers={"Accept-Language": "fr"})
                with urllib.request.urlopen(requete, timeout=30) as reponse:
                    reponse.read()
                    ok = reponse.status == 200
            except (urllib.error.URLError, TimeoutError, ConnectionError):
                ok = False
            ecoule = (time.perf_counter() - debut) * 1000
            with verrou:
                if ok:
                    temps.append(ecoule)
                else:
                    erreurs += 1

    fils = [threading.Thread(target=utilisateur, args=(n,)) for n in range(utilisateurs)]
    depart = time.perf_counter()
    for f in fils:
        f.start()
    for f in fils:
        f.join()
    total = time.perf_counter() - depart

    nb = len(temps) + erreurs
    temps.sort()
    return {
        "utilisateurs": utilisateurs,
        "requetes": nb,
        "debit_rps": round(len(temps) / total, 1) if total else 0,
        "moyenne_ms": round(statistics.fmean(temps), 1) if temps else 0,
        "p95_ms": round(temps[int(len(temps) * 0.95) - 1], 1) if temps else 0,
        "erreurs_pct": round(100 * erreurs / nb, 1) if nb else 0,
    }


def courbe_svg(titre: str, unite: str, points: list[tuple[int, float]], series: dict[str, list[float]]) -> str:
    """Courbe SVG simple : abscisse = utilisateurs simultanés, une ligne par série."""
    largeur, hauteur, marge = 720, 360, 60
    xs = [p[0] for p in points]
    ymax = max((v for valeurs in series.values() for v in valeurs), default=1) * 1.15 or 1
    xmax = max(xs) or 1
    couleurs = ["#ff642d", "#10213a", "#22c55e"]

    def px(x: float) -> float:
        return marge + (x / xmax) * (largeur - 2 * marge)

    def py(y: float) -> float:
        return hauteur - marge - (y / ymax) * (hauteur - 2 * marge)

    morceaux = [
        f'<svg xmlns="http://www.w3.org/2000/svg" width="{largeur}" height="{hauteur}" '
        f'font-family="Arial, sans-serif" font-size="12">',
        f'<rect width="100%" height="100%" fill="#fff"/>',
        f'<text x="{largeur / 2}" y="24" text-anchor="middle" font-size="16" font-weight="bold">{titre}</text>',
        f'<line x1="{marge}" y1="{hauteur - marge}" x2="{largeur - marge}" y2="{hauteur - marge}" stroke="#333"/>',
        f'<line x1="{marge}" y1="{marge}" x2="{marge}" y2="{hauteur - marge}" stroke="#333"/>',
        f'<text x="{largeur / 2}" y="{hauteur - 18}" text-anchor="middle">Utilisateurs simultanés</text>',
        f'<text x="18" y="{hauteur / 2}" text-anchor="middle" transform="rotate(-90 18 {hauteur / 2})">{unite}</text>',
    ]
    for i in range(5):
        valeur = ymax * i / 4
        y = py(valeur)
        morceaux.append(f'<line x1="{marge}" y1="{y}" x2="{largeur - marge}" y2="{y}" stroke="#ddd"/>')
        morceaux.append(f'<text x="{marge - 8}" y="{y + 4}" text-anchor="end">{valeur:.0f}</text>')
    for x in xs:
        morceaux.append(f'<text x="{px(x)}" y="{hauteur - marge + 16}" text-anchor="middle">{x}</text>')
    for n, (nom, valeurs) in enumerate(series.items()):
        couleur = couleurs[n % len(couleurs)]
        trace = " ".join(f"{px(x)},{py(v)}" for x, v in zip(xs, valeurs))
        morceaux.append(f'<polyline points="{trace}" fill="none" stroke="{couleur}" stroke-width="2.5"/>')
        for x, v in zip(xs, valeurs):
            morceaux.append(f'<circle cx="{px(x)}" cy="{py(v)}" r="3.5" fill="{couleur}"/>')
        morceaux.append(f'<rect x="{largeur - marge - 150}" y="{marge + n * 18}" width="12" height="12" fill="{couleur}"/>')
        morceaux.append(f'<text x="{largeur - marge - 132}" y="{marge + n * 18 + 10}">{nom}</text>')
    morceaux.append("</svg>")
    return "\n".join(morceaux)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("--url", default="http://127.0.0.1:8000")
    parser.add_argument("--paliers", default="1,2,5,10,20,30,50")
    parser.add_argument("--duree", type=float, default=10.0, help="secondes par palier")
    args = parser.parse_args()

    dossier = Path(__file__).resolve().parent
    resultats = []
    for n in (int(p) for p in args.paliers.split(",")):
        r = mesurer(args.url.rstrip("/"), n, args.duree)
        resultats.append(r)
        print(f"{n:>3} utilisateurs : {r['debit_rps']:>6} req/s, moyenne {r['moyenne_ms']:>7} ms, "
              f"p95 {r['p95_ms']:>7} ms, erreurs {r['erreurs_pct']} %")

    with open(dossier / "resultats.csv", "w", newline="", encoding="utf-8") as fichier:
        ecrivain = csv.DictWriter(fichier, fieldnames=list(resultats[0]))
        ecrivain.writeheader()
        ecrivain.writerows(resultats)

    points = [(r["utilisateurs"], 0.0) for r in resultats]
    (dossier / "courbe-temps-de-reponse.svg").write_text(
        courbe_svg("Temps de réponse selon la charge", "Millisecondes", points, {
            "Moyenne": [r["moyenne_ms"] for r in resultats],
            "95e centile": [r["p95_ms"] for r in resultats],
        }), encoding="utf-8")
    (dossier / "courbe-debit.svg").write_text(
        courbe_svg("Débit selon la charge", "Requêtes par seconde", points, {
            "Débit": [r["debit_rps"] for r in resultats],
            "Erreurs (%)": [r["erreurs_pct"] for r in resultats],
        }), encoding="utf-8")
    print(f"Résultats : {dossier / 'resultats.csv'} ({datetime.now():%d/%m/%Y %H:%M})")


if __name__ == "__main__":
    main()
