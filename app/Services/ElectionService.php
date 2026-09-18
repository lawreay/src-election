<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class ElectionService
{
    public function getResults(): array
    {
        $pdo = Database::getConnection();
        $election = $this->getCurrentElection();
        if (!$election) {
            return ['election' => null, 'visible' => false, 'positions' => [], 'reconciliation' => null];
        }

        $visible = in_array($election['status'], ['CLOSED', 'RESULTS_FINAL'], true);
        if (!$visible) {
            return ['election' => $election, 'visible' => false, 'positions' => [], 'reconciliation' => null];
        }

        $positionStmt = $pdo->query('SELECT id, name FROM positions WHERE active = 1 ORDER BY sort_order, id');
        $candidateStmt = $pdo->prepare('SELECT c.id, s.first_name, s.last_name, COUNT(b.id) AS votes
            FROM candidates c
            JOIN students s ON s.id = c.student_id
            LEFT JOIN ballot_votes bv ON bv.candidate_id = c.id
            LEFT JOIN ballots b ON b.id = bv.ballot_id AND b.election_id = :election_id
            WHERE c.position_id = :position_id AND c.active = 1
            GROUP BY c.id, s.first_name, s.last_name
            ORDER BY votes DESC, s.last_name, s.first_name');
        $positions = [];
        foreach ($positionStmt->fetchAll() as $position) {
            $candidateStmt->execute([
                ':election_id' => (int) $election['id'],
                ':position_id' => (int) $position['id'],
            ]);
            $positions[] = [
                'name' => $position['name'],
                'candidates' => $candidateStmt->fetchAll(),
            ];
        }

        $reconciliationStmt = $pdo->prepare('SELECT
            (SELECT COUNT(*) FROM students WHERE is_eligible = 1 AND has_voted = 1) AS marked_voters,
            (SELECT COUNT(*) FROM ballots WHERE election_id = :election_id) AS submitted_ballots');
        $reconciliationStmt->execute([':election_id' => (int) $election['id']]);
        $reconciliation = $reconciliationStmt->fetch();
        $reconciliation['matches'] = (int) $reconciliation['marked_voters'] === (int) $reconciliation['submitted_ballots'];

        return [
            'election' => $election,
            'visible' => true,
            'positions' => $positions,
            'reconciliation' => $reconciliation,
        ];
    }

    public function importCandidateList(array $candidateList, string $programme = 'Not specified'): array
    {
        $pdo = Database::getConnection();
        $studentStmt = $pdo->prepare('SELECT id FROM students WHERE first_name = :first_name AND last_name = :last_name LIMIT 1');
        $positionStmt = $pdo->prepare('SELECT id FROM positions WHERE name = :name LIMIT 1');
        $candidateStmt = $pdo->prepare('SELECT id FROM candidates WHERE student_id = :student_id AND position_id = :position_id LIMIT 1');
        $createdStudentStmt = $pdo->prepare('INSERT INTO students (student_number, first_name, last_name, programme, is_eligible, has_voted, created_at, updated_at) VALUES (:student_number, :first_name, :last_name, :programme, 1, 0, datetime("now"), datetime("now"))');
        $createdPositionStmt = $pdo->prepare('INSERT INTO positions (name, sort_order, active, created_at, updated_at) VALUES (:name, :sort_order, 1, datetime("now"), datetime("now"))');
        $candidateInsertStmt = $pdo->prepare('INSERT INTO candidates (student_id, position_id, active, created_at, updated_at) VALUES (:student_id, :position_id, 1, datetime("now"), datetime("now"))');
        $createdStudents = 0;
        $createdPositions = 0;
        $createdCandidates = 0;

        $pdo->beginTransaction();
        try {
            foreach ($candidateList as $positionName => $names) {
                $positionStmt->execute([':name' => $positionName]);
                $positionId = $positionStmt->fetchColumn();
                if (!$positionId) {
                    $sortOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM positions')->fetchColumn();
                    $createdPositionStmt->execute([':name' => $positionName, ':sort_order' => $sortOrder]);
                    $positionId = (int) $pdo->lastInsertId();
                    $createdPositions++;
                }

                foreach ($names as $name) {
                    $parts = preg_split('/\s+/', trim($name), 2);
                    $firstName = $parts[0] ?? '';
                    $lastName = $parts[1] ?? '';
                    if ($firstName === '' || $lastName === '') {
                        continue;
                    }

                    $studentStmt->execute([':first_name' => $firstName, ':last_name' => $lastName]);
                    $studentId = $studentStmt->fetchColumn();
                    if (!$studentId) {
                        $studentNumber = $this->generateStudentNumber($firstName, $lastName);
                        $createdStudentStmt->execute([
                            ':student_number' => $studentNumber,
                            ':first_name' => $firstName,
                            ':last_name' => $lastName,
                            ':programme' => $programme,
                        ]);
                        $studentId = (int) $pdo->lastInsertId();
                        $createdStudents++;
                    }

                    $candidateStmt->execute([':student_id' => $studentId, ':position_id' => $positionId]);
                    if (!$candidateStmt->fetch()) {
                        $candidateInsertStmt->execute([':student_id' => $studentId, ':position_id' => $positionId]);
                        $createdCandidates++;
                    }
                }
            }
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        return [
            'students' => $createdStudents,
            'positions' => $createdPositions,
            'candidates' => $createdCandidates,
        ];
    }

    private function generateStudentNumber(string $firstName, string $lastName): string
    {
        $firstLetters = strtoupper(preg_replace('/[^A-Za-z]/', '', $firstName));
        $lastLetters = strtoupper(preg_replace('/[^A-Za-z]/', '', $lastName));
        $base = str_pad('S' . substr($lastLetters, 0, 3) . substr($firstLetters, 0, 2), 6, 'X');
        $exists = Database::getConnection()->prepare('SELECT id FROM students WHERE student_number = :student_number LIMIT 1');
        for ($sequence = 1; $sequence <= 99; $sequence++) {
            $candidate = $base . str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
            $exists->execute([':student_number' => $candidate]);
            if (!$exists->fetch()) {
                return $candidate;
            }
        }

        throw new \RuntimeException("Unable to generate a unique student ID for {$firstName} {$lastName}.");
    }

    public function getCurrentElection(): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT * FROM elections ORDER BY id DESC LIMIT 1');
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function getStudents(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT * FROM students WHERE is_eligible = 1 ORDER BY last_name, first_name');
        return $stmt->fetchAll();
    }

    public function getPositions(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT * FROM positions ORDER BY sort_order ASC, id ASC');
        return $stmt->fetchAll();
    }

    public function getCandidates(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT c.*, s.student_number, s.first_name, s.last_name, s.programme, p.name AS position_name
            FROM candidates c
            JOIN students s ON s.id = c.student_id
            JOIN positions p ON p.id = c.position_id
            WHERE c.active = 1
            ORDER BY p.sort_order, s.last_name, s.first_name');
        return $stmt->fetchAll();
    }

    public function createElection(string $name, string $startDate, string $endDate): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO elections (name, status, starts_at, ends_at, created_at, updated_at) VALUES (:name, "DRAFT", :starts_at, :ends_at, datetime("now"), datetime("now"))');
        $stmt->execute([
            ':name' => $name,
            ':starts_at' => $startDate,
            ':ends_at' => $endDate,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function updateElectionStatus(int $electionId, string $status): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE elections SET status = :status, updated_at = datetime("now") WHERE id = :id');
        return $stmt->execute([
            ':status' => $status,
            ':id' => $electionId,
        ]);
    }

    public function addPosition(string $name): bool
    {
        $pdo = Database::getConnection();
        $sortOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM positions')->fetchColumn();
        $stmt = $pdo->prepare('INSERT INTO positions (name, sort_order, active, created_at, updated_at) VALUES (:name, :sort_order, 1, datetime("now"), datetime("now"))');
        return $stmt->execute([
            ':name' => $name,
            ':sort_order' => $sortOrder,
        ]);
    }

    public function addCandidate(int $studentId, int $positionId): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO candidates (student_id, position_id, active, created_at, updated_at) VALUES (:student_id, :position_id, 1, datetime("now"), datetime("now"))');
        return $stmt->execute([
            ':student_id' => $studentId,
            ':position_id' => $positionId,
        ]);
    }

    public function findStudentByNumber(string $studentNumber): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM students WHERE student_number = :student_number AND is_eligible = 1 AND has_voted = 0 LIMIT 1');
        $stmt->execute([':student_number' => $studentNumber]);
        return $stmt->fetch() ?: null;
    }

    public function getBallotForStudent(int $studentId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM positions WHERE active = 1 ORDER BY sort_order ASC');
        $stmt->execute();
        $positions = $stmt->fetchAll();

        $ballot = [];
        foreach ($positions as $position) {
            $candidates = $pdo->prepare('SELECT c.id, c.position_id, s.student_number, s.first_name, s.last_name, s.programme
                FROM candidates c
                JOIN students s ON s.id = c.student_id
                WHERE c.position_id = :position_id AND c.active = 1 AND s.id != :student_id
                ORDER BY s.last_name, s.first_name');
            $candidates->execute([
                ':position_id' => $position['id'],
                ':student_id' => $studentId,
            ]);
            $ballot[] = [
                'position' => $position,
                'candidates' => $candidates->fetchAll(),
            ];
        }

        return $ballot;
    }

    public function submitBallot(int $studentId, int $electionId, array $votes): bool
    {
        $pdo = Database::getConnection();
        $electionStmt = $pdo->prepare('SELECT id FROM elections WHERE id = :id AND status = :status LIMIT 1');
        $electionStmt->execute([':id' => $electionId, ':status' => 'OPEN']);
        if (!$electionStmt->fetch()) {
            return false;
        }

        $pdo->beginTransaction();

        try {
            $markStmt = $pdo->prepare('UPDATE students SET has_voted = 1, updated_at = datetime("now") WHERE id = :id AND is_eligible = 1 AND has_voted = 0');
            $markStmt->execute([':id' => $studentId]);
            if ($markStmt->rowCount() !== 1) {
                $pdo->rollBack();
                return false;
            }

            $validVoteStmt = $pdo->prepare('SELECT c.id FROM candidates c WHERE c.id = :candidate_id AND c.position_id = :position_id AND c.active = 1 AND c.student_id != :student_id');
            $ballotStmt = $pdo->prepare('INSERT INTO ballots (election_id, ballot_uuid, submitted_at) VALUES (:election_id, :ballot_uuid, datetime("now"))');
            $ballotUuid = bin2hex(random_bytes(12));
            $ballotStmt->execute([
                ':election_id' => $electionId,
                ':ballot_uuid' => $ballotUuid,
            ]);
            $ballotId = (int) $pdo->lastInsertId();

            foreach ($votes as $positionId => $candidateId) {
                $validVoteStmt->execute([
                    ':candidate_id' => (int) $candidateId,
                    ':position_id' => (int) $positionId,
                    ':student_id' => $studentId,
                ]);
                if (!$validVoteStmt->fetch()) {
                    throw new \InvalidArgumentException('Invalid candidate selection.');
                }

                $voteStmt = $pdo->prepare('INSERT INTO ballot_votes (ballot_id, position_id, candidate_id) VALUES (:ballot_id, :position_id, :candidate_id)');
                $voteStmt->execute([
                    ':ballot_id' => $ballotId,
                    ':position_id' => (int) $positionId,
                    ':candidate_id' => (int) $candidateId,
                ]);
            }

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return false;
        }
    }

    public function findStudentById(int $studentId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM students WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $studentId]);
        return $stmt->fetch() ?: null;
    }

    public function getDashboardStats(): array
    {
        $pdo = Database::getConnection();
        $stats = [];
        $stats['total_students'] = (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
        $stats['voted_students'] = (int) $pdo->query('SELECT COUNT(*) FROM students WHERE has_voted = 1')->fetchColumn();
        $stats['remaining_students'] = max(0, $stats['total_students'] - $stats['voted_students']);
        $stats['positions'] = (int) $pdo->query('SELECT COUNT(*) FROM positions WHERE active = 1')->fetchColumn();
        $stats['candidates'] = (int) $pdo->query('SELECT COUNT(*) FROM candidates WHERE active = 1')->fetchColumn();
        $stats['ballots'] = (int) $pdo->query('SELECT COUNT(*) FROM ballots')->fetchColumn();

        return $stats;
    }
}
