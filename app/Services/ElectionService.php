<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class ElectionService
{
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
