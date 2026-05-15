<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\UserMatchResultDTO;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Service for matching external platform users to local users.
 *
 * Implements a two-tier matching strategy:
 * 1. Exact email match (highest confidence)
 * 2. Name similarity match as fallback (variable confidence)
 *
 * Name matching uses normalization and similarity scoring to handle
 * cases where employees have different emails across platforms but
 * consistent names.
 */
final class UserMatchingService
{
    /**
     * Minimum name similarity percentage required for a match.
     * Values below this threshold are not considered valid matches.
     */
    private const NAME_SIMILARITY_THRESHOLD = 85.0;

    /**
     * Find a matching local user based on email and/or name.
     *
     * Matching priority:
     * 1. Exact match on users.email (primary email)
     * 2. Exact match on user_external_identities.external_email (cross-platform)
     * 3. Name similarity match above threshold
     *
     * @param  string|null  $email  Email from external platform
     * @param  string|null  $name  Full name from external platform (for Odoo, DeskTime, SystemPin)
     * @param  string|null  $firstName  First name (for ProofHub)
     * @param  string|null  $lastName  Last name (for ProofHub)
     * @return UserMatchResultDTO Result containing matched user and match details
     */
    public function findMatchingUser(
        ?string $email,
        ?string $name = null,
        ?string $firstName = null,
        ?string $lastName = null,
    ): UserMatchResultDTO {
        // Normalize the external name for comparison
        $normalizedExternalName = $this->normalizeFullName($name, $firstName, $lastName);

        // Step 1: Try exact email match on primary email
        if ($email) {
            $normalizedEmail = $this->normalizeEmail($email);
            $user = User::where('email', $normalizedEmail)->first();

            if ($user) {
                Log::debug('UserMatchingService: Matched by primary email', [
                    'email' => $normalizedEmail,
                    'user_id' => $user->id,
                ]);

                return UserMatchResultDTO::emailMatch($user, 'email');
            }
        }

        // Step 2: Try email match on external identities (cross-platform email)
        if ($email) {
            $normalizedEmail = $this->normalizeEmail($email);
            $user = User::whereHas('externalIdentities', function ($query) use ($normalizedEmail): void {
                $query->where('external_email', $normalizedEmail);
            })->first();

            if ($user) {
                Log::debug('UserMatchingService: Matched by cross-platform email', [
                    'email' => $normalizedEmail,
                    'user_id' => $user->id,
                ]);

                return UserMatchResultDTO::emailMatch($user, 'cross_platform_email');
            }
        }

        // Step 3: Fall back to name similarity matching
        if ($normalizedExternalName !== '') {
            $result = $this->findByNameSimilarity($normalizedExternalName);

            if ($result->hasMatch()) {
                Log::debug('UserMatchingService: Matched by name similarity', [
                    'external_name' => $normalizedExternalName,
                    'user_id' => $result->user->id,
                    'user_name' => $result->user->name,
                    'confidence' => $result->confidence,
                ]);

                return $result;
            }
        }

        Log::debug('UserMatchingService: No match found', [
            'email' => $email,
            'name' => $normalizedExternalName,
        ]);

        return UserMatchResultDTO::noMatch();
    }

    /**
     * Normalize a full name for comparison.
     *
     * Handles different name formats:
     * - Full name string (Odoo, DeskTime, SystemPin)
     * - Separate first/last name (ProofHub)
     *
     * Normalization steps:
     * - Trim whitespace
     * - Convert to lowercase
     * - Remove accents/diacritics
     * - Collapse multiple spaces
     *
     * @param  string|null  $name  Full name (if available)
     * @param  string|null  $firstName  First name (if available)
     * @param  string|null  $lastName  Last name (if available)
     * @return string Normalized name, empty string if no name provided
     */
    public function normalizeFullName(?string $name, ?string $firstName = null, ?string $lastName = null): string
    {
        // If we have separate first/last name (ProofHub format), combine them
        if ($firstName !== null || $lastName !== null) {
            $parts = array_filter([
                $firstName ? trim($firstName) : null,
                $lastName ? trim($lastName) : null,
            ]);
            $fullName = implode(' ', $parts);
        } else {
            $fullName = $name ?? '';
        }

        return $this->normalizeName($fullName);
    }

    /**
     * Normalize a name string for comparison.
     *
     * @param  string  $name  The name to normalize
     * @return string Normalized name
     */
    private function normalizeName(string $name): string
    {
        // Trim and collapse whitespace
        $normalized = preg_replace('/\s+/', ' ', trim($name));

        // Convert to lowercase
        $normalized = mb_strtolower($normalized, 'UTF-8');

        // Remove accents/diacritics using transliteration
        $normalized = $this->removeAccents($normalized);

        return $normalized;
    }

    /**
     * Remove accents and diacritics from a string.
     *
     * Converts characters like "é" to "e", "ñ" to "n", etc.
     *
     * @param  string  $string  The string to process
     * @return string String with accents removed
     */
    private function removeAccents(string $string): string
    {
        // Manual replacement of common accented characters (most reliable cross-platform)
        $accents = [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c', 'ý' => 'y', 'ÿ' => 'y',
            'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Å' => 'A',
            'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'Õ' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
            'Ñ' => 'N', 'Ç' => 'C', 'Ý' => 'Y',
        ];

        return strtr($string, $accents);
    }

    /**
     * Normalize an email address.
     *
     * @param  string  $email  The email to normalize
     * @return string Normalized email (lowercase, trimmed)
     */
    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * Find a user by name similarity.
     *
     * Compares the external name against all local user names and returns
     * the best match above the similarity threshold.
     *
     * @param  string  $externalName  Normalized external name
     * @return UserMatchResultDTO Result with best matching user or no match
     */
    private function findByNameSimilarity(string $externalName): UserMatchResultDTO
    {
        // Get all active users for comparison
        // Note: For large user bases, consider optimizing with database-level search
        $users = User::active()->get(['id', 'name']);

        $bestMatch = null;
        $bestSimilarity = 0.0;

        foreach ($users as $user) {
            $normalizedLocalName = $this->normalizeName($user->name);
            $similarity = $this->calculateNameSimilarity($externalName, $normalizedLocalName);

            if ($similarity > $bestSimilarity) {
                $bestSimilarity = $similarity;
                $bestMatch = $user;
            }
        }

        // Only return match if above threshold
        if ($bestMatch && $bestSimilarity >= self::NAME_SIMILARITY_THRESHOLD) {
            // Reload the full user model for the match
            $fullUser = User::find($bestMatch->id);

            return UserMatchResultDTO::nameMatch($fullUser, $bestSimilarity);
        }

        return UserMatchResultDTO::noMatch();
    }

    /**
     * Calculate similarity between two name strings.
     *
     * Uses PHP's similar_text() function which calculates the number of
     * matching characters between two strings and returns a percentage.
     *
     * @param  string  $name1  First name (normalized)
     * @param  string  $name2  Second name (normalized)
     * @return float Similarity percentage (0-100)
     */
    private function calculateNameSimilarity(string $name1, string $name2): float
    {
        if ($name1 === '' || $name2 === '') {
            return 0.0;
        }

        // Exact match
        if ($name1 === $name2) {
            return 100.0;
        }

        // Use similar_text for similarity scoring
        similar_text($name1, $name2, $percent);

        return $percent;
    }

    /**
     * Get the similarity threshold used for name matching.
     *
     * @return float The threshold percentage
     */
    public function getNameSimilarityThreshold(): float
    {
        return self::NAME_SIMILARITY_THRESHOLD;
    }
}
