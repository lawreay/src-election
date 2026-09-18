<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class StudentImportService
{
    public function deleteStudent(int $studentId): array
    {
        $pdo = Database::getConnection();
        $studentStmt = $pdo->prepare('SELECT has_voted FROM students WHERE id = :id LIMIT 1');
        $studentStmt->execute([':id' => $studentId]);
        $student = $studentStmt->fetch();

        if (!$student) {
            return ['success' => false, 'message' => 'Student not found.'];
        }

        if ((int) $student['has_voted'] === 1) {
            return ['success' => false, 'message' => 'A student who has voted cannot be deleted.'];
        }

        $candidateStmt = $pdo->prepare('SELECT id FROM candidates WHERE student_id = :student_id LIMIT 1');
        $candidateStmt->execute([':student_id' => $studentId]);
        if ($candidateStmt->fetch()) {
            return ['success' => false, 'message' => 'A student assigned as a candidate cannot be deleted.'];
        }

        $deleteStmt = $pdo->prepare('DELETE FROM students WHERE id = :id AND has_voted = 0');
        $deleteStmt->execute([':id' => $studentId]);

        return $deleteStmt->rowCount() === 1
            ? ['success' => true, 'message' => 'Student deleted successfully.']
            : ['success' => false, 'message' => 'Student could not be deleted.'];
    }

    public function createStudent(string $firstName, string $lastName, string $programme): array
    {
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        $programme = trim($programme);

        if ($firstName === '' || $lastName === '' || $programme === '') {
            return ['success' => false, 'message' => 'All student fields are required.'];
        }

        $pdo = Database::getConnection();
        $studentNumber = $this->generateStudentNumber($firstName, $lastName);

        $stmt = $pdo->prepare('INSERT INTO students (student_number, first_name, last_name, programme, is_eligible, has_voted, created_at, updated_at) VALUES (:student_number, :first_name, :last_name, :programme, 1, 0, datetime("now"), datetime("now"))');
        $stmt->execute([
            ':student_number' => $studentNumber,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':programme' => $programme,
        ]);

        return ['success' => true, 'student_number' => $studentNumber, 'message' => "Student added successfully with ID {$studentNumber}."];
    }

    public function generateStudentNumber(string $firstName, string $lastName): string
    {
        $firstLetters = strtoupper(preg_replace('/[^A-Za-z]/', '', $firstName));
        $lastLetters = strtoupper(preg_replace('/[^A-Za-z]/', '', $lastName));
        $base = 'S' . substr($lastLetters, 0, 3) . substr($firstLetters, 0, 2);
        $base = str_pad($base, 6, 'X');

        $pdo = Database::getConnection();
        $candidate = $base . '01';
        $sequence = 1;
        $exists = $pdo->prepare('SELECT id FROM students WHERE student_number = :student_number LIMIT 1');

        do {
            $exists->execute([':student_number' => $candidate]);
            if (!$exists->fetch()) {
                return $candidate;
            }

            $sequence++;
            $candidate = $base . str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
        } while ($sequence <= 99);

        throw new \RuntimeException("Unable to generate a unique student ID for {$firstName} {$lastName}.");
    }

    public function importCsv(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => 'CSV file not found.'];
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return ['success' => false, 'message' => 'Unable to open CSV file.'];
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return ['success' => false, 'message' => 'CSV file is empty.'];
        }

        $expected = ['first_name', 'last_name', 'programme'];
        $legacyExpected = ['student_number', 'first_name', 'last_name', 'programme'];
        $headerNormalized = array_map('strtolower', array_map('trim', $header));

        if ($headerNormalized !== $expected && $headerNormalized !== $legacyExpected) {
            fclose($handle);
            return ['success' => false, 'message' => 'CSV header must be: first_name,last_name,programme'];
        }

        $pdo = Database::getConnection();
        $inserted = 0;
        $skipped = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($headerNormalized)) {
                $skipped++;
                continue;
            }

            $row = array_map('trim', $row);
            if ($headerNormalized === $legacyExpected) {
                [, $firstName, $lastName, $programme] = $row;
            } else {
                [$firstName, $lastName, $programme] = $row;
            }

            if ($firstName === '' || $lastName === '' || $programme === '') {
                $errors[] = 'Missing required field for one row.';
                $skipped++;
                continue;
            }

            $studentNumber = $this->generateStudentNumber($firstName, $lastName);
            $stmt = $pdo->prepare('INSERT INTO students (student_number, first_name, last_name, programme, is_eligible, has_voted, created_at, updated_at) VALUES (:student_number, :first_name, :last_name, :programme, 1, 0, datetime("now"), datetime("now"))');
            $stmt->execute([
                ':student_number' => $studentNumber,
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':programme' => $programme,
            ]);

            $inserted++;
        }

        fclose($handle);

        return [
            'success' => true,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => $inserted . ' students imported.',
        ];
    }
}
