-- Create the database
CREATE DATABASE IF NOT EXISTS gestion_absences;
USE gestion_absences;

-- Create tables
CREATE TABLE IF NOT EXISTS administrateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS filieres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS responsables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    nom VARCHAR(100) NOT NULL,
    filiere_id INT NOT NULL,
    responsable_id INT NOT NULL,
    semestre ENUM('S1', 'S2') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id) ON DELETE CASCADE,
    FOREIGN KEY (responsable_id) REFERENCES responsables(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS etudiants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_apogee VARCHAR(20) NOT NULL UNIQUE,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    filiere_id INT NOT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    verification_code VARCHAR(100) DEFAULT NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS inscriptions_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id INT NOT NULL,
    module_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    UNIQUE KEY (etudiant_id, module_id)
);

CREATE TABLE IF NOT EXISTS seances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT NOT NULL,
    date_seance DATE NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS absences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id INT NOT NULL,
    seance_id INT NOT NULL,
    justifie BOOLEAN DEFAULT FALSE,
    justificatif VARCHAR(255) DEFAULT NULL,
    absent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE,
    FOREIGN KEY (seance_id) REFERENCES seances(id) ON DELETE CASCADE,
    UNIQUE KEY (etudiant_id, seance_id)
);

CREATE TABLE etudiants_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id INT,
    module_id INT,
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id),
    FOREIGN KEY (module_id) REFERENCES modules(id)
);
-- Insert default admin
INSERT INTO administrateurs (username, password, nom, prenom, email) 
VALUES ('admin', '$2y$10$klsJqCwrJf5Ouap6laeYa.efkvfpUERPN29ctKklWQCHfKaLEA9t2', 'Admin', 'Super', 'admin@example.com');
       

-- Password: Admin123!


-- Insert sample data for testing
INSERT INTO filieres (code, nom, description) VALUES 
('GI', 'Génie Informatique', 'Formation en informatique et génie logiciel'),
('RSSP', 'Réseaux et Sécurité des Systèmes et Produits', 'Formation en réseaux et sécurité informatique'),
('GIL', 'Génie Industriel et Logistique', 'Formation en génie industriel et logistique'),
('GCDSTE', 'Génie Cyber-Défense et Systèmes de Télécommunications Embarqués', 'Formation en Cyber-Défense et Systèmes de Télécommunications Embarqués'),
('SEECS', 'Systèmes Electroniques Embarqués et Commande des Systèmes', 'Formation en Electroniques Embarqués et Commande des Systèmes');

INSERT INTO responsables (nom, prenom,password, email) VALUES
('Bouarifi', 'Walid','$2y$10$RQ6lpyflraKP9fFKGK0IW.V0IaORR0PTB5aaFrfj7j8qZ0jAruf2S
', 'w.bouarifi@example.com'),
('Zrikem', 'Maria','$2y$10$RQ6lpyflraKP9fFKGK0IW.V0IaORR0PTB5aaFrfj7j8qZ0jAruf2S', 'M.zrikem@example.com'),
('atlas', 'abdelghafour','$2y$10$RQ6lpyflraKP9fFKGK0IW.V0IaORR0PTB5aaFrfj7j8qZ0jAruf2S', 'A.atlas@example.com');

-- Insert modules for GI
INSERT INTO modules (code, nom, filiere_id, responsable_id, semestre) VALUES
('GI-S1-M1', 'Analyse des données', 1, 1, 'S1'),
('GI-S1-M2', 'Programmation orientée objet & C++', 1, 2, 'S1'),
('GI-S1-M3', 'Protocoles de Communications', 1, 3, 'S1'),
('GI-S1-M4', 'CALCUL SCIENTIFIQUE', 1, 1, 'S1'),
('GI-S1-M5', 'Systèmes d’information & Bases de données relationnelles.', 1, 2, 'S1'),
('GI-S1-M6', 'Digital Skills : Excel avancé', 1, 3, 'S1'),
('GI-S1-M7', 'Langues Etrangéres (Anglais /Français)', 1, 1, 'S1'),
('GI-S2-M1', 'Génie Logiciel', 1, 2, 'S2'),
('GI-S2-M2', 'Modélisation Avancée pour la Décision', 1, 3, 'S2'),
('GI-S2-M3', 'Systèmes d’exploitation et Unix', 1, 1, 'S2'),
('GI-S2-M4', 'Conception et Développement d’Applications Web avec PHP et MySQL', 1, 2, 'S2'),
('GI-S2-M5', 'Culture and Art skills', 1, 3, 'S2'),
('GI-S2-M7', 'Langues Etrangéres (Anglais /Français)', 1, 3, 'S2');
-- Insert modules for RSSP
INSERT INTO modules (code, nom, filiere_id, responsable_id, semestre) VALUES
('RSSP-S1-M1', 'Routage et Commutation 1', 2, 2, 'S1'),
('RSSP-S1-M2', 'INGENIERIE LOGICIELLE AVEC JAVA', 2, 3, 'S1'),
('RSSP-S2-M1', 'Modèles et Algorithmes pour la Décision (MAD)', 2, 1, 'S2'),
('RSSP-S2-M2', 'Types d’attaques et Ethical Hacking', 2, 3, 'S2');
-- Insert modules for GIL
INSERT INTO modules (code, nom, filiere_id, responsable_id, semestre) VALUES
('GIL-S1-M1', 'Systèmes d’information & bases de données relationnelles', 3, 1, 'S1'),
('GIL-S1-M2', 'Électrotechnique et Électronique de puissance',3, 2, 'S1'),
('GIL-S2-M1', 'Contrôle Commande des Systèmes de production', 3, 1, 'S2'),
('GIL-S2-M2', 'Résistance des Matériaux RDM',3, 3, 'S2');
-- Insert modules for GCDSTE
INSERT INTO modules (code, nom, filiere_id, responsable_id, semestre) VALUES
('GCDSTE-S1-M1', 'Traitement de Signal',4, 2, 'S1'),
('GCDSTE-S1-M2', 'Algorithmique et programmation en Python', 4, 1, 'S1'),
('GCDSTE-S2-M1', 'Systèmes de Transmissions Numérique', 4, 3, 'S2'),
('GCDSTE-S2-M2', 'Ingénierie Web & Système d’information', 4, 1, 'S2');
-- Insert modules for SEECS
INSERT INTO modules (code, nom, filiere_id, responsable_id, semestre) VALUES
('SEECS-S1-M1', 'ELECTROTECHNIQUE INDUSTRIELLE', 5, 3, 'S1'),
('SEECS-S1-M2', 'AUTOMATIQUE DES SYSTEMES LINEAIRES CONTINUS', 5, 2, 'S1'),
('SEECS-S2-M1', 'INSTRUMENTATION INDUSTRIELLE', 5, 3, 'S2'),
('SEECS-S2-M2', 'AUTOMATISME ET AUTOMATE PROGRAMMABLES INDUSTRIELS', 5, 1, 'S2');
-- Table pour les justificatifs d'absence
CREATE TABLE justificatifs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id INT NOT NULL,
    module_id INT NOT NULL,
    date_absence DATE NOT NULL,
    fichier_path VARCHAR(255) NOT NULL,
    statut ENUM('en attente', 'accepté', 'rejeté') DEFAULT 'en attente',
    date_soumission TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    commentaire TEXT,
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    INDEX idx_etudiant_module (etudiant_id, module_id),
    INDEX idx_date_absence (date_absence),
    INDEX idx_statut (statut)
);
CREATE TABLE qr_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(255) NOT NULL UNIQUE,
    responsable_id INT NOT NULL,
    seance_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (responsable_id) REFERENCES responsables(id) ON DELETE CASCADE,
    FOREIGN KEY (seance_id) REFERENCES seances(id) ON DELETE CASCADE
);