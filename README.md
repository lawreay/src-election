# SRC Election Management System

A small offline-first PHP + SQLite election system for a technical college SRC election.

## Goals

- Admin creates and manages the election
- Students are imported in bulk from CSV
- Eligible students vote once anonymously
- Ballot data is separated from voter identity
- Election results are hidden until closure
- Final reconciliation checks vote totals against marked voters

## Tech stack

- PHP 8.2+
- SQLite
- HTML + CSS + Bootstrap
- No external dependencies required

## Quick start

1. Ensure PHP is installed.
2. Start the app:
   `php -S localhost:8000 -t public`
3. Open `http://localhost:8000/login`
4. Log in with:
   - Username: `admin`
   - Password: `admin123`

## Important design rule

The system records who has voted, but intentionally does not store which student is linked to a submitted ballot.

This preserves the secret ballot principle while still preventing double voting.
