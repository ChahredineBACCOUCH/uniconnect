"""
Génère les supports de cours PDF de la démonstration (SCRUM-188).

Ces PDF sont publiés par DemoProductionSeeder dans le groupe « Bases de données »
puis indexés pour l'Assistant IA : chaque page porte un contenu distinct pour que
les citations « [n] fichier · p. X » soient vérifiables pendant la démo.

Usage (depuis backend/database/seeders/demo) : python generer_pdf.py
Dépendance : reportlab (pip install reportlab). Les PDF générés sont versionnés,
il n'est donc pas nécessaire de relancer ce script pour la démo.
"""

from pathlib import Path

from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import getSampleStyleSheet
from reportlab.lib.units import cm
from reportlab.platypus import PageBreak, Paragraph, SimpleDocTemplate, Spacer, Preformatted

DOSSIER = Path(__file__).resolve().parent
STYLES = getSampleStyleSheet()

COURS = {
    "chapitre-1-modele-relationnel.pdf": (
        "Chapitre 1 – Le modèle relationnel",
        [
            ("Relations, attributs et domaines", [
                "Le modèle relationnel, proposé par Edgar F. Codd en 1970, représente les données sous forme de tables appelées relations.",
                "Une relation possède un nom et un ensemble d'attributs (les colonnes). Chaque ligne de la table est un n-uplet (ou tuple).",
                "Le domaine d'un attribut est l'ensemble des valeurs qu'il peut prendre : par exemple, le domaine de l'attribut note est l'ensemble des réels entre 0 et 20.",
                "Le degré d'une relation est son nombre d'attributs ; sa cardinalité est son nombre de n-uplets.",
                "Exemple : ETUDIANT(matricule, nom, prenom, date_naissance, code_classe) est une relation de degré 5.",
            ]),
            ("Clés primaires et clés étrangères", [
                "Une clé candidate est un ensemble minimal d'attributs qui identifie de manière unique chaque n-uplet de la relation.",
                "La clé primaire est la clé candidate choisie pour identifier les n-uplets ; on la souligne dans le schéma. Elle ne peut jamais être NULL.",
                "Une clé étrangère est un attribut (ou un groupe d'attributs) qui fait référence à la clé primaire d'une autre relation.",
                "Exemple : dans ETUDIANT, code_classe est une clé étrangère qui référence CLASSE(code_classe, libelle, niveau).",
                "Une clé étrangère permet de relier les tables entre elles sans dupliquer l'information.",
            ]),
            ("Contraintes d'intégrité", [
                "L'intégrité d'entité impose que la clé primaire de chaque n-uplet soit unique et non nulle.",
                "L'intégrité référentielle impose que toute valeur d'une clé étrangère existe comme valeur de clé primaire dans la relation référencée (ou soit NULL si c'est autorisé).",
                "Les contraintes de domaine vérifient le type et les valeurs permises, par exemple CHECK (note BETWEEN 0 AND 20).",
                "En SQL, on peut préciser le comportement en cas de suppression : ON DELETE CASCADE supprime les lignes dépendantes, ON DELETE SET NULL met la clé étrangère à NULL.",
            ]),
            ("Du schéma à SQL", [
                "Chaque relation devient une table créée avec CREATE TABLE, en déclarant la clé primaire avec PRIMARY KEY et les clés étrangères avec FOREIGN KEY ... REFERENCES.",
                "Exemple de création :",
                "CODE:CREATE TABLE etudiant (\n  matricule   VARCHAR(12) PRIMARY KEY,\n  nom         VARCHAR(50) NOT NULL,\n  code_classe VARCHAR(10) REFERENCES classe(code_classe)\n);",
                "À retenir : une base relationnelle bien conçue évite la redondance et garantit la cohérence grâce aux contraintes d'intégrité.",
            ]),
        ],
    ),
    "chapitre-2-normalisation.pdf": (
        "Chapitre 2 – Dépendances fonctionnelles et normalisation",
        [
            ("Dépendances fonctionnelles", [
                "Il existe une dépendance fonctionnelle X -> Y lorsque deux n-uplets qui ont la même valeur pour X ont forcément la même valeur pour Y.",
                "Exemple : matricule -> nom, prenom. Connaître le matricule d'un étudiant permet de connaître son nom et son prénom.",
                "Une dépendance X -> Y est élémentaire si aucune partie stricte de X ne détermine déjà Y.",
                "Une dépendance X -> Z est transitive s'il existe Y tel que X -> Y et Y -> Z, avec Y qui ne détermine pas X.",
            ]),
            ("Première et deuxième forme normale", [
                "Une relation est en première forme normale (1FN) si tous ses attributs sont atomiques : pas de liste de valeurs dans une même cellule.",
                "Une relation est en deuxième forme normale (2FN) si elle est en 1FN et si tout attribut non clé dépend de toute la clé primaire, et non d'une partie seulement.",
                "Exemple de violation de la 2FN : INSCRIPTION(matricule, code_matiere, nom_etudiant, note), car nom_etudiant ne dépend que de matricule.",
                "Correction : on sépare en ETUDIANT(matricule, nom_etudiant) et INSCRIPTION(matricule, code_matiere, note).",
            ]),
            ("Troisième forme normale et BCNF", [
                "Une relation est en troisième forme normale (3FN) si elle est en 2FN et si aucun attribut non clé ne dépend transitivement de la clé.",
                "Exemple de violation de la 3FN : ETUDIANT(matricule, code_classe, libelle_classe), car matricule -> code_classe -> libelle_classe.",
                "Correction : on crée CLASSE(code_classe, libelle_classe) et on garde code_classe comme clé étrangère dans ETUDIANT.",
                "La forme normale de Boyce-Codd (BCNF) est plus stricte : pour toute dépendance X -> Y non triviale, X doit être une clé candidate.",
            ]),
            ("Pourquoi normaliser ?", [
                "La normalisation supprime la redondance et donc les anomalies de mise à jour : modifier un libellé de classe à un seul endroit suffit.",
                "Elle évite les anomalies d'insertion (impossible d'ajouter une classe sans étudiant) et de suppression (perdre une classe en supprimant son dernier étudiant).",
                "En pratique, on vise au moins la 3FN. On peut dénormaliser volontairement pour des raisons de performance, en le documentant.",
                "Méthode : lister les dépendances fonctionnelles, trouver la clé, puis décomposer la relation forme normale par forme normale.",
            ]),
        ],
    ),
    "chapitre-3-curseurs-plsql.pdf": (
        "Chapitre 3 – Les curseurs PL/SQL",
        [
            ("Curseur implicite et curseur explicite", [
                "Un curseur est une zone de travail allouée par Oracle pour exécuter une requête SQL et parcourir son résultat.",
                "Oracle crée un curseur implicite pour chaque instruction SELECT ... INTO, INSERT, UPDATE ou DELETE. On consulte son état avec SQL%ROWCOUNT ou SQL%FOUND.",
                "Un curseur explicite est déclaré par le programmeur quand une requête renvoie plusieurs lignes que l'on veut traiter une par une.",
                "Déclaration : CURSOR c_etudiants IS SELECT matricule, nom FROM etudiant WHERE code_classe = 'L2-INFO-A';",
            ]),
            ("OPEN, FETCH et CLOSE", [
                "Le cycle de vie d'un curseur explicite comporte trois étapes : OPEN exécute la requête, FETCH lit la ligne suivante dans des variables, CLOSE libère la zone de travail.",
                "Exemple complet :",
                "CODE:DECLARE\n  CURSOR c_etu IS SELECT nom FROM etudiant;\n  v_nom etudiant.nom%TYPE;\nBEGIN\n  OPEN c_etu;\n  LOOP\n    FETCH c_etu INTO v_nom;\n    EXIT WHEN c_etu%NOTFOUND;\n    DBMS_OUTPUT.PUT_LINE(v_nom);\n  END LOOP;\n  CLOSE c_etu;\nEND;",
                "Oublier CLOSE laisse le curseur ouvert : le nombre de curseurs ouverts par session est limité (paramètre OPEN_CURSORS).",
            ]),
            ("Les attributs d'un curseur explicite", [
                "%FOUND vaut TRUE si le dernier FETCH a ramené une ligne.",
                "%NOTFOUND vaut TRUE si le dernier FETCH n'a ramené aucune ligne ; on l'utilise pour sortir de la boucle (EXIT WHEN c%NOTFOUND).",
                "%ROWCOUNT donne le nombre de lignes lues depuis l'ouverture du curseur.",
                "%ISOPEN vaut TRUE si le curseur est ouvert ; il évite d'ouvrir deux fois le même curseur, ce qui lève l'erreur CURSOR_ALREADY_OPEN.",
            ]),
            ("Boucle FOR et curseurs paramétrés", [
                "La boucle FOR de curseur ouvre, lit et ferme le curseur automatiquement : FOR r IN c_etu LOOP ... END LOOP;",
                "C'est l'écriture recommandée : le code est plus court et le curseur est toujours fermé, même en cas d'erreur dans la boucle.",
                "Un curseur paramétré reçoit des valeurs à l'ouverture : CURSOR c_classe(p_code VARCHAR2) IS SELECT nom FROM etudiant WHERE code_classe = p_code;",
                "On l'ouvre avec OPEN c_classe('L2-INFO-A') ou FOR r IN c_classe('L3-INFO') LOOP. Le TP noté porte sur ces deux écritures.",
            ]),
        ],
    ),
}


def generer(nom_fichier: str, titre: str, sections: list) -> None:
    doc = SimpleDocTemplate(
        str(DOSSIER / nom_fichier), pagesize=A4, title=titre, author="UniConnect – démonstration",
        leftMargin=2.2 * cm, rightMargin=2.2 * cm, topMargin=2 * cm, bottomMargin=2 * cm,
    )
    elements = []
    for i, (sous_titre, paragraphes) in enumerate(sections):
        if i:
            elements.append(PageBreak())
        elements.append(Paragraph(titre, STYLES["Title"]))
        elements.append(Paragraph(f"{i + 1}. {sous_titre}", STYLES["Heading2"]))
        elements.append(Spacer(1, 0.3 * cm))
        for texte in paragraphes:
            if texte.startswith("CODE:"):
                elements.append(Preformatted(texte[5:], STYLES["Code"]))
            else:
                elements.append(Paragraph(texte, STYLES["BodyText"]))
            elements.append(Spacer(1, 0.25 * cm))
    doc.build(elements)


if __name__ == "__main__":
    for fichier, (titre, sections) in COURS.items():
        generer(fichier, titre, sections)
        print("généré :", fichier)
