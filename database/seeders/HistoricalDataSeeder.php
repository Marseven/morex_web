<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class HistoricalDataSeeder extends Seeder
{
    private $userId;
    private $accountId;
    private $categories = [];

    // Mapping des descriptions vers catégories
    private $categoryMapping = [
        // REVENUS
        'income' => [
            'salaire' => 'Salaire',
            'agatour' => 'Projets/Freelance',
            'agt' => 'Projets/Freelance',
            'africakard' => 'Projets/Freelance',
            'afk' => 'Projets/Freelance',
            'techies' => 'Projets/Freelance',
            'jobs' => 'Projets/Freelance',
            'vxp' => 'Projets/Freelance',
            'koumbaya' => 'Projets/Freelance',
            'n&h' => 'Projets/Freelance',
            'nh' => 'Projets/Freelance',
            'artiaf' => 'Projets/Freelance',
            'sigalli' => 'Projets/Freelance',
            'quinbride' => 'Projets/Freelance',
            'jaco' => 'Projets/Freelance',
            'g24' => 'Projets/Freelance',
            'kayzer' => 'Projets/Freelance',
            'primea' => 'Projets/Freelance',
            'mbira' => 'Projets/Freelance',
            '3mpc' => 'Projets/Freelance',
            'urban' => 'Projets/Freelance',
            'sloan' => 'Projets/Freelance',
            'fond d\'urgence' => 'Autres revenus',
            'caisse urgence' => 'Autres revenus',
            'retour pret' => 'Remboursements',
            'dette' => 'Remboursements',
            'prêt' => 'Remboursements',
            'pret' => 'Remboursements',
        ],

        // DEPENSES
        'expense' => [
            'dimes' => 'Dîmes',
            'dîmes' => 'Dîmes',
            'dime' => 'Dîmes',
            'loyer' => 'Logement',
            'emilie' => 'Emilie/Courses',
            'course' => 'Emilie/Courses',
            'courses' => 'Emilie/Courses',
            'sortie' => 'Sorties/Loisirs',
            'sorties' => 'Sorties/Loisirs',
            'restaurant' => 'Restaurants',
            'repas' => 'Alimentation',
            'nourriture' => 'Alimentation',
            'nouriture' => 'Alimentation',
            'taxi' => 'Transport',
            'transport' => 'Transport',
            'don' => 'Fonds d\'Aide/Dons',
            'dons' => 'Fonds d\'Aide/Dons',
            'cadeau' => 'Événements',
            'anniversaire' => 'Événements',
            'anniv' => 'Événements',
            'mariage' => 'Événements',
            'deuil' => 'Événements',
            'veillé' => 'Événements',
            'epargne' => 'Épargne/Investissement',
            'épargne' => 'Épargne/Investissement',
            'epargnes' => 'Épargne/Investissement',
            'tontine' => 'Épargne/Investissement',
            'internet' => 'Abonnements',
            'canalbox' => 'Abonnements',
            'canal box' => 'Abonnements',
            'netflix' => 'Abonnements',
            'canva' => 'Abonnements',
            'icloud' => 'Abonnements',
            'apple' => 'Abonnements',
            'prime' => 'Abonnements',
            'crunchyroll' => 'Abonnements',
            'spotify' => 'Abonnements',
            'figma' => 'Abonnements',
            'claude' => 'Abonnements',
            'abonnement' => 'Abonnements',
            'abonnements' => 'Abonnements',
            'stream' => 'Abonnements',
            'frais banque' => 'Factures',
            'frais de banque' => 'Factures',
            'charge banque' => 'Factures',
            'banque' => 'Factures',
            'eau' => 'Factures',
            'gaz' => 'Factures',
            'electricien' => 'Divers',
            'plombier' => 'Divers',
            'soudure' => 'Divers',
            'santé' => 'Santé',
            'hôpital' => 'Santé',
            'hopital' => 'Santé',
            'médicament' => 'Santé',
            'medicament' => 'Santé',
            'pharmacie' => 'Santé',
            'dette' => 'Divers',
            'dettes' => 'Divers',
            'pret' => 'Divers',
            'prêt' => 'Divers',
            'coiffure' => 'Divers',
            'pressing' => 'Divers',
            'linge' => 'Divers',
            'linges' => 'Divers',
            'foot' => 'Sorties/Loisirs',
            'football' => 'Sorties/Loisirs',
            'sport' => 'Sorties/Loisirs',
            'billard' => 'Sorties/Loisirs',
            'auto ecole' => 'Divers',
            'auto école' => 'Divers',
            'examen' => 'Divers',
            'achat' => 'Shopping',
            'maillot' => 'Shopping',
            'téléphone' => 'Équipement',
            'telephone' => 'Équipement',
            'ecouteur' => 'Équipement',
            'chargeur' => 'Équipement',
            'climatisation' => 'Équipement',
            'cuve' => 'Équipement',
            'surpresseur' => 'Équipement',
            'site' => 'Divers',
            'nettoyage' => 'Divers',
            'parents' => 'Fonds d\'Aide/Dons',
            'famille' => 'Fonds d\'Aide/Dons',
            'belle famille' => 'Fonds d\'Aide/Dons',
            'belle mere' => 'Événements',
            'mere' => 'Événements',
            'père' => 'Événements',
            'eglise' => 'Dîmes',
            'cotisation' => 'Divers',
            'association' => 'Divers',
            'contribution' => 'Dîmes',
            'action de grace' => 'Dîmes',
            'location' => 'Événements',
            'reception' => 'Événements',
            'boisson' => 'Alimentation',
            'haribo' => 'Alimentation',
            'divers' => 'Divers',
            'mood' => 'Divers',
            'gimac' => 'Divers',
            'top tek' => 'Divers',
            'tailleur' => 'Shopping',
            'paola' => 'Fonds d\'Aide/Dons',
        ],
    ];

    public function run(): void
    {
        // Trouver l'utilisateur par email
        $user = User::where('email', 'mebodoaristide@gmail.com')->first();

        if (!$user) {
            $this->command->error('Utilisateur non trouvé. Exécutez d\'abord UserSeeder.');
            return;
        }

        $this->userId = $user->id;
        $this->loadCategories();
        $this->createAccountIfNeeded();

        // Données par mois (Mars 2025 - Janvier 2026)
        $this->importMars2025();
        $this->importAvril2025();
        $this->importMai2025();
        $this->importJuin2025();
        $this->importJuillet2025();
        $this->importAout2025();
        $this->importSeptembre2025();
        $this->importOctobre2025();
        $this->importNovembre2025();
        $this->importDecembre2025();
        $this->importJanvier2026();

        $this->command->info('Import terminé !');
    }

    private function loadCategories(): void
    {
        // Charger les catégories système (user_id = null) et utilisateur
        $categories = Category::where('is_system', true)
            ->orWhere('user_id', $this->userId)
            ->get();

        foreach ($categories as $cat) {
            $this->categories[$cat->name] = $cat->id;
        }

        $this->command->info(count($this->categories) . ' catégories chargées');
    }

    private function createAccountIfNeeded(): void
    {
        $account = Account::firstOrCreate(
            ['user_id' => $this->userId, 'is_default' => true],
            [
                'id' => Str::uuid(),
                'name' => 'Compte Principal',
                'type' => 'checking',
                'balance' => 0,
                'color' => '#6366F1',
                'icon' => 'wallet',
                'display_order' => 0,
            ]
        );

        $this->accountId = $account->id;
        $this->command->info('Compte: ' . $account->name);
    }

    private function getCategoryId(string $description, string $type): string
    {
        $desc = strtolower(trim($description));

        // Chercher dans le mapping
        $mapping = $this->categoryMapping[$type] ?? [];

        foreach ($mapping as $keyword => $categoryName) {
            if (str_contains($desc, $keyword)) {
                if (isset($this->categories[$categoryName])) {
                    return $this->categories[$categoryName];
                }
            }
        }

        // Catégorie par défaut
        if ($type === 'income') {
            return $this->categories['Autres revenus'] ?? $this->categories['Projets/Freelance'];
        }

        return $this->categories['Divers'] ?? $this->categories['Autres dépenses'];
    }

    private function parseAmount(string $text): int
    {
        // Nettoyer et extraire le montant
        $text = strtolower(trim($text));
        $text = str_replace(['- ', '- '], '', $text);

        // Extraire le nombre
        preg_match('/(\d+(?:[.,]\d+)?)\s*k?/i', $text, $matches);

        if (empty($matches[1])) {
            return 0;
        }

        $amount = floatval(str_replace(',', '.', $matches[1]));

        // Si le montant contient 'k' ou est < 1000, multiplier par 1000
        if (stripos($text, 'k') !== false || $amount < 1000) {
            $amount *= 1000;
        }

        return (int) $amount;
    }

    private function createTransaction(
        string $description,
        int $amount,
        string $type,
        int $year,
        int $month,
        int $day = 15
    ): void {
        if ($amount <= 0) {
            return;
        }

        $categoryId = $this->getCategoryId($description, $type);
        $date = sprintf('%04d-%02d-%02d', $year, $month, min($day, 28));

        // Extraire le bénéficiaire si présent
        $beneficiary = $this->extractBeneficiary($description);
        $desc = ucfirst(trim($description));

        // Éviter les doublons en vérifiant si la transaction existe déjà
        Transaction::firstOrCreate(
            [
                'user_id' => $this->userId,
                'date' => $date,
                'amount' => $amount,
                'type' => $type,
                'description' => $desc,
            ],
            [
                'id' => Str::uuid(),
                'account_id' => $this->accountId,
                'category_id' => $categoryId,
                'beneficiary' => $beneficiary,
            ]
        );
    }

    private function extractBeneficiary(string $description): ?string
    {
        // Noms communs à extraire
        $names = ['emilie', 'yvan', 'menes', 'joanis', 'zaina', 'olivia', 'abdoul', 'mederik',
                  'frederic', 'arthur', 'stan', 'gliyane', 'steeve', 'andré', 'stephy', 'maxime',
                  'jude', 'darlouise', 'marielle', 'ariane', 'eric', 'franck', 'guillaume',
                  'paola', 'joyce', 'zara', 'klorane'];

        $desc = strtolower($description);
        foreach ($names as $name) {
            if (str_contains($desc, $name)) {
                return ucfirst($name);
            }
        }

        return null;
    }

    private function importMonth(array $incomes, array $expenses, int $year, int $month): void
    {
        $day = 1;

        foreach ($incomes as $income) {
            $amount = $this->parseAmount($income);
            if ($amount > 0) {
                $this->createTransaction($income, $amount, 'income', $year, $month, $day);
                $day++;
            }
        }

        $day = 1;
        foreach ($expenses as $expense) {
            $amount = $this->parseAmount($expense);
            if ($amount > 0) {
                $this->createTransaction($expense, $amount, 'expense', $year, $month, $day);
                $day++;
            }
        }

        $this->command->info("Mois $month/$year importé");
    }

    // ===== DONNÉES PAR MOIS =====

    private function importMars2025(): void
    {
        $incomes = [
            '700k Salaire', '100K Avance Jaco', '50K Techies', '100K Jobs', '40K AFR',
            '20K Artiaf', '265k Jaco', '100K Jaco', '50K Quinbride', '25k Sloan',
            '250k Jaco', '50K AFK', '30k G24'
        ];

        $expenses = [
            '100k site', '50k Auto Ecole', '20K Nettoyage', '70k Dimes', '12k Frais banque',
            '110K Emilie', '50k Cadeau Maman', '20k Dettes', '10k Sortie', '5k Divers',
            '10K Transport', '25k Foot', '25k Sortie', '10k taxi', '80k Achat',
            '15k Dimes', '5k taxi', '15K Sortie', '10k mood', '100K Achat',
            '205k tontine', '5k Sortie', '10k Sortie', '10k taxi', '25K Canal Box',
            '10K don', '5K don', '25k Ariane', '60k Sortie', '20K prêt',
            '10K transport', '60k dime', '18K sortie', '5K coiffure', '5K taxi',
            '20k Emilie', '10k Charlene don', '10K Sortie', '10K taxi', '42k prêt',
            '10K dime', '10k sortie', '10K divers', '225k Loyer'
        ];

        $this->importMonth($incomes, $expenses, 2025, 3);
    }

    private function importAvril2025(): void
    {
        $incomes = [
            '800K Salaire', '50K Agatour', '50K Agatour', '15K Africakard', '40k Yvan prêt',
            '3k Yvan prêt', '30k Africakard', '170k Agatour', '15k Menes prêt', '20k Joanis prêt',
            '100k Jobs', '50k Techies', '20k Artiaf', '20k Zaina prêt', '50k Sigalli',
            '22k Olivia', '3k Olivia', '50k Abdoul', '70k VXP', '10k Mederik prêt',
            '10k Frederic prêt'
        ];

        $expenses = [
            '80k Dimes', '70k Anniv Aude', '60k Courses', '50k Emilie', '10k Don',
            '50k Sortie', '80k Dette', '20k Sortie', '5k Don', '225k Loyer',
            '205k Epargne', '50k Sortie', '250k Pret', '3k Maillot', '30k Pret',
            '20k Pret', '5k Taxi', '7k Sortie', '10k Sortie', '20k Anniv Aude',
            '5k Eau', '10k Pressing', '25k Sortie', '5k Don', '25k Sortie',
            '10k Nourriture', '20k Anniv Mere', '20k Emilie', '10k Emilie', '10k Pressing',
            '10k Canva', '3k iCloud', '25k Internet', '20k Sortie', '30k Don Mami',
            '10k Sortie', '10k Taxi', '5k Anniv Bee', '60k Examen Auto Ecole', '5k Nourriture',
            '5k Taxi', '5k Don', '10k Sortie Billard', '5k Nourriture', '10k Taxi',
            '70k Dîmes', '4k Prime Video', '4K Nourriture', '12k Restaurant', '5k Eau',
            '10k Coiffure', '3k Boisson', '15k sortie'
        ];

        $this->importMonth($incomes, $expenses, 2025, 4);
    }

    private function importMai2025(): void
    {
        $incomes = [
            '790K Salaire', '100k Agatour', '100k JOBS', '50k Techies', '60k Agatour'
        ];

        $expenses = [
            '40k Sortie', '15k Sortie', '5k taxi', '12k Frais de banque', '15k Don André',
            '10k Don Stephy', '6k Contribution Eglise', '205k Epargne', '200k Action de Grace Eglise',
            '25k Sortie', '5k taxi', '10k Repas', '50k Location Salle Codon', '50k Santé Emilie',
            '35k Auto Ecole', '15k Sortie', '10k Don', '5k Taxi', '8k Sortie',
            '10k Cadeau', '5k Gaz', '5k Cadeau Steeve', '7k Crunchyroll', '3k Apple',
            '25k Internet', '5k taxi', '7k Netflix', '6k Sortie', '10k Nourriture',
            '3k coiffure', '7k sortie', '10k Sortie', '20k Sortie', '9k Hôpital',
            '10k Sortie', '5k Taxi', '15k Sortie', '50k Cadeau Darlouise'
        ];

        $this->importMonth($incomes, $expenses, 2025, 5);
    }

    private function importJuin2025(): void
    {
        $incomes = [
            '790K Salaire', '50k Agatour', '20k Dette', '440k Fond urgence', '30k Retour Pret',
            '50k Techies', '100k JOBS', '50k Dette'
        ];

        $expenses = [
            '75k Dîmes', '100k Anniversaire Emilie', '5k Taxi', '10k Sortie', '20k Dettes',
            '225k Loyer', '15k Sortie', '15k Pret', '4k Primes', '40k Sortie',
            '5k Anniversaire Jude', '5k Don', '80k Pénalité Billet', '5k Cadeau Maxime',
            '5k Cotisation Eglise', '20k Médicaments', '15k Pret', '15k Pret', '120k Pret',
            '5k Foot', '5k Sortie', '3k Taxi', '20k Abonnements', '205k Epargne',
            '20k Dons', '3k Taxi', '25k Internet', '10k Nourriture', '50k Anniversaire Belle Mere',
            '12k Claude', '12k Frais banque', '3k Taxi', '5k Nourriture', '5k Sortie',
            '4k Sport', '7k Crunchyroll', '3k taxi', '3k Coiffure', '2k Eau',
            '5k don', '10k Anniversaire Frederic', '10k Don', '35k Prêt Klorane', '10k Gimac',
            '10k Sortie', '25k Anniversaire Frederic', '15k Linge', '20k Emilie', '20k Football',
            '5k taxi', '5k taxi', '10k Sortie'
        ];

        $this->importMonth($incomes, $expenses, 2025, 6);
    }

    private function importJuillet2025(): void
    {
        $incomes = [
            '790K Salaire', '50k Techies', '20k Artiaf', '150k Caisse Urgence', '75k Africakard'
        ];

        $expenses = [
            '85k Dîmes', '205k Epargne', '20k Gliyane', '55k Dette Arthur', '30k Stan',
            '15k Sortie', '5k Taxi', '20k Sortie', '10k Dette Frederic', '10k Climatisation',
            '5k Eau', '20k Don', '100k Emilie Maison', '5k don', '12k Frais de banque',
            '10k Sortie', '15k Sortie', '10k Emilie', '10k Frederic', '15k Mariage Wissidiel',
            '6k Nourriture', '12k Claude', '22k Sortie', '5k Sortie', '10k Sortie',
            '10k Sortie', '25k CanalBox', '225k Loyer', '10k Emilie', '25k Don',
            '15k Sortie', '10k taxi', '10k Don', '10k sortie', '25k Tech Slam Event',
            '20k Taxi Repas', '3k Sortie', '15k Achat Voyage', '14k sortie', '10k Don',
            '5k Don', '5k Taxi', '20k Emilie', '10k Divers Akieni'
        ];

        $this->importMonth($incomes, $expenses, 2025, 7);
    }

    private function importAout2025(): void
    {
        $incomes = [
            '800K Salaire', '360k Koumbaya', '50k Techies', '20k Artiaf', '295k Jaco FM',
            '100k NH', '50k AFK', '30k Dette'
        ];

        $expenses = [
            '80k Dîmes', '205k Epargnes', '225k Loyer', '5k Eau', '100k Course Emilie',
            '12k Charge Banque', '20k Sortie', '5k Gaz', '5k don', '20k Sortie',
            '10k Eric', '15k Sortie', '5k Taxi', '50k Epargne', '250k Linges',
            '50k Emilie', '36k Dimes', '5k Dimes', '5k eau Yvon', '15k Deuil Houtsa',
            '5k Nouriture', '5k Nourriture', '4k Sortie', '25k CanalBox', '10k Sortie Foot',
            '30k anniversaire Marielle', '5k taxi', '15k Aide Ami', '5k taxi', '7k sortie',
            '120k Téléphone Emilie', '3k Coiffure', '10k Sortie', '25k Sortie', '10k Ecouteur',
            '70k Dîmes', '50k Epargne', '10k Sortie', '5k Anniversaire DA', '30k Sortie'
        ];

        $this->importMonth($incomes, $expenses, 2025, 8);
    }

    private function importSeptembre2025(): void
    {
        $incomes = [
            '800k Salaire', '150k Koumbaya', '10k Delberg', '150k Koumbaya', '80k Techies',
            '100k Koumbaya', '50k AFK', '40k NH', '360k Kayzer', '410k Primea'
        ];

        $expenses = [
            '80k Dîmes', '225k Loyer', '108k Anniversaire Moi-même', '10k sortie', '10k Emilie',
            '100k Emilie Course', '5k Eric', '10k Nourriture', '5k Medicament', '15k Ampoule Eric',
            '17k Sortie', '56k Dîmes', '60k Sortie', '20k Anniversaire Mami', '205k Epargnes',
            '10k Don', '12k Frais de Banque', '5k Don', '10k Sortie', '5k Haribo',
            '50k Sortie Restaurant', '10k Sortie', '5k Taxi', '5k taxi', '10k Haribo',
            '25k Canalbox Internet', '32k Sortie', '100k Dîmes', '25k Foot cotisation',
            '22k abonnement en ligne', '30k Hôpital Emilie', '5k Nouriture', '10k Sortie',
            '57k Claude IA', '15k Sortie', '20k Besoin de la maison', '8k Chargeurs Téléphone',
            '7k Nouriture', '4k Don', '7k Sortie', '10k Sortie', '70k Soiree en couple',
            '20k Anniv Joanis', '25k Don', '500k Epargne', '15k Pharmacie', '9k Figma'
        ];

        $this->importMonth($incomes, $expenses, 2025, 9);
    }

    private function importOctobre2025(): void
    {
        $incomes = [
            '800k Salaire', '20k Artiaf', '180k Techies Urban', '100k Koumbaya',
            '100k Koumbaya', '50k N&H'
        ];

        $expenses = [
            '80k Dîmes', '56k Dîmes', '100k Parents', '30k Nourritures', '5k Sortie',
            '20k Don', '5k Prime video', '225k Loyer', '30k Don Steeve', '20k Sortie',
            '25k Sortie', '110k Course Emilie', '300k Installation Cuve Surpresseur', '70k Sortie',
            '10k Veillé Darlouise', '25k Don Ariane', '5k Nourriture', '5k sortie', '15k Sortie',
            '10k Course', '12k Emilie', '20k Plombier', '30k Sortie', '60k Claude Code IA',
            '7k Netflix', '12k Eau', '25k Internet', '62k Belle Famille', '17k Divers',
            '10k Sortie', '20k Emilie', '25k Sortie', '10k Don', '10k Cordia',
            '10k Linge', '200k Emilie', '25k Sortie', '20k Electricien', '12k Eau',
            '50k Sortie', '10k Taxi Nourriture'
        ];

        $this->importMonth($incomes, $expenses, 2025, 10);
    }

    private function importNovembre2025(): void
    {
        $incomes = [
            '800k Salaire', '150k Mbira', '100k Jaco Web', '180k Techies', '20k Artiaf', '75k AFK'
        ];

        $expenses = [
            '80k Dîmes', '15k Figma', '16k Abonnements', '52k Soudure', '10k Don Eglise',
            '225k Loyer', '110k Emilie Course', '10k Don Franck', '10k Don', '30k Cadeau Emilie',
            '15k Sortie', '20k Dîmes', '45k Dîmes Octobre', '12k Frais de Banque', '10k Eau',
            '25k sortie', '30k Sortie', '10k don', '17k Emilie', '30k Deuil Guillaume',
            '17k Sortie', '10k Sortie', '11k Don', '10k Taxi Medicament', '67k Restaurant Famille',
            '4k Chargeurs', '10k Nourriture', '5k Linge', '10k Emilie', '5k Don',
            '10k Restaurant', '12k Restaurant', '10k Eau', '20k Sortie', '30k Santé',
            '70k Emilie Santé', '20k sortie', '15k sortie', '10k Sortie', '10k Anniversaire',
            '15k Sortie', '7k Association'
        ];

        $this->importMonth($incomes, $expenses, 2025, 11);
    }

    private function importDecembre2025(): void
    {
        $incomes = [
            '800k Salaire', '50k Agatour', '100k Jobs', '80k Techies', '100k Techies'
        ];

        $expenses = [
            '80k Dîmes', '15k Figma', '16k Abonnements', '225k Loyer', '50k Dette Zara',
            '50k Emilie', '15k Sortie', '5k Hôpital', '20k Don Eglise', '60k Sortie',
            '20k Cadeau Père', '25k Restaurant', '15k Sortie', '12k Frais de banque',
            '20k sortie', '60k Course', '15k Course', '30k Sortie', '10k Eau',
            '15k Course', '5k Don', '20k Dimes', '5k Sortie', '3k course',
            '15k Sortie', '3k Coiffure', '20k Sortie', '20k Course', '6k Linge',
            '50k Sortie', '60k Claude IA', '7k Repas', '20k Emilie', '25k internet',
            '20k Dettes', '20k CodOn', '20k Sortie', '10k Eau', '15k sortie'
        ];

        $this->importMonth($incomes, $expenses, 2025, 12);
    }

    private function importJanvier2026(): void
    {
        $incomes = [
            '700k Salaire', '100k 3MPC', '100k AFK', '40k AGT'
        ];

        $expenses = [
            '70k Dîmes', '60k Courses', '20k Don Noel', '25k Sortie', '10k Tailleur',
            '20k Courses Noel', '25k Sortie', '225k Loyer', '50k Emilie', '30k Paola',
            '50k Cadeau Joyce', '20k Sortie', '26k Sortie', '10k Sortie', '35k Reception Amis',
            '12k Banque', '15k Top Tek', '50k Emilie', '10k Eau', '17k sortie',
            '50k Hôpital', '30k Sortie', '75k Hôpital', '15k Don', '20k Sortie',
            '25k internet', '60k Claude Code', '30k Abonnements Stream', '30k Sortie',
            '15k Restaurant', '40k Taxi Nourriture'
        ];

        $this->importMonth($incomes, $expenses, 2026, 1);
    }
}
