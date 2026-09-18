<?php

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Session;
use App\Helpers\View;
use App\Services\AuthService;
use App\Services\ElectionService;
use App\Services\StudentImportService;

$session = new Session();
$authService = new AuthService($session);
$electionService = new ElectionService();
$studentImportService = new StudentImportService();

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = rtrim($uri, '/');
if ($uri === '') {
    $uri = '/';
}

$messages = ['error' => $session->get('flash_error'), 'success' => $session->get('flash_success')];
$session->forget('flash_error');
$session->forget('flash_success');

try {
    if ($uri === '/login') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if ($authService->login($username, $password)) {
                View::redirect('/dashboard');
            }

            $session->set('flash_error', 'Invalid admin credentials.');
            View::redirect('/login');
        }

        include __DIR__ . '/../resources/views/login.php';
        exit;
    }

    if ($uri === '/logout') {
        $authService->logout();
        View::redirect('/login');
    }

    if (!$session->isLoggedIn() && $uri !== '/login') {
        View::redirect('/login');
    }

    if ($uri === '/dashboard') {
        $stats = $electionService->getDashboardStats();
        include __DIR__ . '/../resources/views/dashboard.php';
        exit;
    }

    if ($uri === '/students') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $studentImportService->createStudent(
                (string) ($_POST['first_name'] ?? ''),
                (string) ($_POST['last_name'] ?? ''),
                (string) ($_POST['programme'] ?? '')
            );
            $session->set($result['success'] ? 'flash_success' : 'flash_error', $result['message']);
            View::redirect('/students');
        }

        $students = $electionService->getStudents();
        include __DIR__ . '/../resources/views/students.php';
        exit;
    }

    if ($uri === '/students/delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $result = $studentImportService->deleteStudent((int) ($_POST['student_id'] ?? 0));
        $session->set($result['success'] ? 'flash_success' : 'flash_error', $result['message']);
        View::redirect('/students');
    }

    if ($uri === '/students/import') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
            $tmpName = $_FILES['csv_file']['tmp_name'];
            $result = $studentImportService->importCsv($tmpName);
            $session->set('flash_success', $result['message']);
            View::redirect('/students');
        }

        include __DIR__ . '/../resources/views/students-import.php';
        exit;
    }

    if ($uri === '/positions') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim((string) ($_POST['position_name'] ?? ''));
            if ($name !== '') {
                $electionService->addPosition($name);
                $session->set('flash_success', 'Position added.');
            }
            View::redirect('/positions');
        }

        $positions = $electionService->getPositions();
        include __DIR__ . '/../resources/views/positions.php';
        exit;
    }

    if ($uri === '/candidates') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $studentId = (int) ($_POST['student_id'] ?? 0);
            $positionId = (int) ($_POST['position_id'] ?? 0);

            if ($studentId > 0 && $positionId > 0) {
                $electionService->addCandidate($studentId, $positionId);
                $session->set('flash_success', 'Candidate assigned.');
            }
            View::redirect('/candidates');
        }

        $students = $electionService->getStudents();
        $positions = $electionService->getPositions();
        $candidates = $electionService->getCandidates();
        include __DIR__ . '/../resources/views/candidates.php';
        exit;
    }

    if ($uri === '/election') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $status = strtoupper(trim((string) ($_POST['status'] ?? '')));
            $currentElection = $electionService->getCurrentElection();
            if ($status !== '' && $currentElection) {
                $allowedStatuses = ['DRAFT', 'READY', 'OPEN', 'CLOSED', 'RESULTS_FINAL'];
                if (in_array($status, $allowedStatuses, true)) {
                    $electionService->updateElectionStatus((int) $currentElection['id'], $status);
                    $session->set('flash_success', 'Election status updated.');
                }
                View::redirect('/election');
            }

            $name = trim((string) ($_POST['name'] ?? ''));
            $start = (string) ($_POST['start_date'] ?? '');
            $end = (string) ($_POST['end_date'] ?? '');

            if ($name !== '') {
                $electionService->createElection($name, $start, $end);
                $session->set('flash_success', 'Election created.');
            }
            View::redirect('/election');
        }

        $election = $electionService->getCurrentElection();
        include __DIR__ . '/../resources/views/election.php';
        exit;
    }

    if ($uri === '/voting') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $studentNumber = trim((string) ($_POST['student_number'] ?? ''));
            $student = $electionService->findStudentByNumber($studentNumber);
            if ($student) {
                $session->set('voting_student_id', (int) $student['id']);
                $session->set('flash_success', 'Student found. Continue to ballot.');
                View::redirect('/voting/ballot');
            }

            $session->set('flash_error', 'Student not found or not eligible.');
            View::redirect('/voting');
        }

        include __DIR__ . '/../resources/views/voting.php';
        exit;
    }

    if ($uri === '/voting/ballot') {
        $studentId = (int) $session->get('voting_student_id', 0);
        if ($studentId <= 0) {
            View::redirect('/voting');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $votes = $_POST['vote'] ?? [];
            $election = $electionService->getCurrentElection();
            $success = $electionService->submitBallot($studentId, (int) ($election['id'] ?? 0), $votes);

            if ($success) {
                $session->forget('voting_student_id');
                $session->set('flash_success', 'Ballot submitted successfully.');
                View::redirect('/voting/success');
            }

            $session->set('flash_error', 'Ballot failed. The student may already have voted or the selection was invalid.');
            View::redirect('/voting');
        }

        $student = $electionService->findStudentById($studentId);
        $ballot = $electionService->getBallotForStudent($studentId);
        include __DIR__ . '/../resources/views/ballot.php';
        exit;
    }

    if ($uri === '/voting/success') {
        include __DIR__ . '/../resources/views/voting-success.php';
        exit;
    }

    if ($uri === '/') {
        View::redirect('/login');
    }

    http_response_code(404);
    echo 'Page not found';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'System error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
