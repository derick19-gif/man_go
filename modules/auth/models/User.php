<?php
namespace App\Models;

use Database;
use Security;

/**
 * User Model

 * 
 * Handles user data and database operations
 */

class User
{
    /**
     * Database connection
     * 
     * @var \PDO
     */
    private \PDO $db;

    /**
     * User data
     * 
     * @var array
     */
    private array $data = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Find user by email
     * 
     * @param string $email
     * @return self|null
     */
    public function findByEmail(string $email): ?self
    {
        $stmt = $this->db->prepare("
            SELECT u.*, GROUP_CONCAT(r.slug) as roles
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE u.email = :email AND u.deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([':email' => $email]);
        $result = $stmt->fetch();

        if (!$result) {
            return null;
        }

        $this->data = $result;
        return $this;
    }

    /**
     * Find user by phone
     * 
     * @param string $phone
     * @return self|null
     */
    public function findByPhone(string $phone): ?self
    {
        $stmt = $this->db->prepare("
            SELECT u.*, GROUP_CONCAT(r.slug) as roles
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE u.phone = :phone AND u.deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([':phone' => $phone]);
        $result = $stmt->fetch();

        if (!$result) {
            return null;
        }

        $this->data = $result;
        return $this;
    }

    /**
     * Find user by ID
     * 
     * @param int $id
     * @return self|null
     */
    public function findById(int $id): ?self
    {
        $stmt = $this->db->prepare("
            SELECT u.*, GROUP_CONCAT(r.slug) as roles
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE u.id = :id AND u.deleted_at IS NULL
            GROUP BY u.id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();

        if (!$result) {
            return null;
        }

        $this->data = $result;
        return $this;
    }

    /**
     * Create a new user
     * 
     * @param array $data
     * @return bool
     */
    public function create(array $data): bool
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO users (
                    uuid, first_name, last_name, email, password, phone,
                    country_id, locale_id, currency_id, timezone_id,
                    is_active, is_verified, created_at, updated_at
                ) VALUES (
                    UUID(), :first_name, :last_name, :email, :password, :phone,
                    :country_id, :locale_id, :currency_id, :timezone_id,
                    :is_active, :is_verified, NOW(), NOW()
                )
            ");

            $stmt->execute([
                ':first_name' => $data['first_name'] ?? null,
                ':last_name' => $data['last_name'] ?? null,
                ':email' => $data['email'],
                ':password' => Security::hashPassword($data['password']),
                ':phone' => $data['phone'] ?? null,
                ':country_id' => $data['country_id'] ?? null,
                ':locale_id' => $data['locale_id'] ?? null,
                ':currency_id' => $data['currency_id'] ?? null,
                ':timezone_id' => $data['timezone_id'] ?? null,
                ':is_active' => $data['is_active'] ?? 1,
                ':is_verified' => $data['is_verified'] ?? 0,
            ]);

            $userId = $this->db->lastInsertId();
            $this->data = array_merge($data, ['id' => $userId]);

            // Fetch the created user
            $this->findById($userId);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update user
     * 
     * @param array $data
     * @return bool
     */
    public function update(array $data): bool
    {
        if (empty($this->data['id'])) {
            throw new \RuntimeException('Cannot update user without ID');
        }

        $allowed = ['first_name', 'last_name', 'phone', 'country_id', 'locale_id', 'currency_id', 'timezone_id', 'is_active'];
        $updateData = [];

        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (empty($updateData)) {
            return true;
        }

        $sets = [];
        $params = [':id' => $this->data['id']];

        foreach ($updateData as $field => $value) {
            $sets[] = "{$field} = :{$field}";
            $params[":{$field}"] = $value;
        }

        $stmt = $this->db->prepare("
            UPDATE users
            SET " . implode(', ', $sets) . ", updated_at = NOW()
            WHERE id = :id
        ");

        $result = $stmt->execute($params);
        $this->data = array_merge($this->data, $updateData);

        return $result;
    }

    /**
     * Update password
     * 
     * @param string $password
     * @return bool
     */
    public function updatePassword(string $password): bool
    {
        if (empty($this->data['id'])) {
            throw new \RuntimeException('Cannot update password without user ID');
        }

        $stmt = $this->db->prepare("
            UPDATE users
            SET password = :password, updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':password' => Security::hashPassword($password),
            ':id' => $this->data['id'],
        ]);
    }

    /**
     * Update last login
     * 
     * @return bool
     */
    public function updateLastLogin(): bool
    {
        if (empty($this->data['id'])) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE users
            SET last_login = NOW(), updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([':id' => $this->data['id']]);
    }

    /**
     * Verify password
     * 
     * @param string $password
     * @return bool
     */
    public function verifyPassword(string $password): bool
    {
        if (empty($this->data['password'])) {
            return false;
        }

        // Check if password needs rehashing
        if (Security::needsRehash($this->data['password'])) {
            $this->updatePassword($password);
        }

        return Security::verifyPassword($password, $this->data['password']);
    }

    /**
     * Check if user is active
     * 
     * @return bool
     */
    public function isActive(): bool
    {
        return (bool)($this->data['is_active'] ?? false);
    }

    /**
     * Check if user is verified
     * 
     * @return bool
     */
    public function isVerified(): bool
    {
        return (bool)($this->data['is_verified'] ?? false);
    }

    /**
     * Get user roles
     * 
     * @return array
     */
    public function getRoles(): array
    {
        if (empty($this->data['roles'])) {
            return [];
        }

        return array_filter(explode(',', $this->data['roles']));
    }

    /**
     * Check if user has role
     * 
     * @param string $role
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    /**
     * Get user data
     * 
     * @param string|null $key
     * @return mixed
     */
    public function getData(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->data;
        }

        return $this->data[$key] ?? null;
    }

    /**
     * Get user ID
     * 
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->data['id'] ?? null;
    }

    /**
     * Get user email
     * 
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->data['email'] ?? null;
    }

    /**
     * Check if exists
     * 
     * @return bool
     */
    public function exists(): bool
    {
        return !empty($this->data['id']);
    }
}
