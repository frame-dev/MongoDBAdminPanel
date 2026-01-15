# Security Documentation

## 🔒 Security Features

This MongoDB Admin Panel includes enterprise-grade security features to protect against common web vulnerabilities.

---

## 🛡️ Implemented Security Measures

### 1. **CSRF Protection** (Cross-Site Request Forgery)
- **What it prevents:** Attackers from tricking authenticated users into performing unwanted actions
- **How it works:** Each session gets a unique 32-byte random token that must be included in all dangerous operations
- **Protected operations:** Delete, Update, Bulk Update, Import, Delete All
- **Implementation:** `config/security.php` - `generateCSRFToken()` and `verifyCSRFToken()`

```php
// All dangerous forms include:
<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

// Server validates before execution:
if (!verifyCSRFToken($_POST['csrf_token'])) {
    // Reject request and log violation
}
```

---

### 2. **Rate Limiting**
- **What it prevents:** Brute force attacks and DoS attempts
- **Limits:** 30 requests per 60 seconds per action per session
- **Scope:** Applies to all POST operations (add, update, delete, import, etc.)
- **Response:** Returns error message when exceeded, logs security event
- **Implementation:** Session-based counter with timestamp tracking

```php
if (!checkRateLimit('post_action', 30, 60)) {
    // Reject and log
}
```

---

### 3. **Input Sanitization**
- **What it prevents:** XSS (Cross-Site Scripting) attacks
- **Method:** `htmlspecialchars()` with `ENT_QUOTES` and UTF-8 encoding
- **Scope:** All user inputs including form fields, template names, collection names
- **Recursion:** Handles nested arrays automatically
- **Implementation:** `sanitizeInput()` function

```php
$cleanInput = sanitizeInput($_POST['field_name']);
```

---

### 4. **JSON Validation**
- **What it prevents:** Code injection through malicious JSON
- **Blocked patterns:**
  - `$where` - MongoDB server-side JavaScript execution
  - `eval()` - JavaScript code evaluation
  - `function` - Function definitions
  - `constructor` - Constructor manipulation
- **Used in:** Document creation, template saving, query building
- **Implementation:** Pattern matching before JSON decoding

```php
if (!validateJSON($jsonString)) {
    throw new Exception('Invalid or dangerous JSON');
}
```

---

### 5. **MongoDB Query Sanitization**
- **What it prevents:** NoSQL injection attacks
- **Method:** Whitelist-based operator validation
- **Allowed operators:** `$eq`, `$ne`, `$gt`, `$gte`, `$lt`, `$lte`, `$in`, `$nin`, `$regex`, `$exists`, `$or`, `$and`
- **All others:** Automatically stripped from queries
- **Implementation:** `sanitizeMongoQuery()` function

---

### 6. **Field & Collection Name Validation**
- **Collection names:**
  - Alphanumeric, underscore, dash only
  - Max 64 characters
  - Cannot start with `system.`
- **Field names:**
  - Cannot start with `$` (prevents operator injection)
  - Cannot contain null bytes
- **Implementation:** Regex validation functions

```php
validateCollectionName($name); // Returns true/false
validateFieldName($field);      // Returns true/false
```

---

### 7. **File Upload Security**
- **Max size:** 5 MB
- **Allowed types:** JSON and text files only
- **Validation:**
  - MIME type checking
  - File extension validation
  - Content verification before processing
- **Implementation:** `validateUpload()` function

---

### 8. **Security Event Logging**
- **Logged events:**
  - CSRF violations
  - Rate limit exceeded
  - Invalid input attempts
  - Failed validations
  - Backup creation/restore
- **Log location:** `logs/security.log`
- **Information captured:**
  - Timestamp
  - IP address
  - Session ID
  - User agent
  - Event type
  - Details (sanitized)

```log
[2026-01-14 12:34:56] EVENT: csrf_violation | IP: 192.168.1.100 | Session: abc123...
```

---

### 9. **Audit Trail**
- **Storage:** `_audit_log` collection in MongoDB
- **Tracked operations:**
  - Database backups
  - Bulk updates
  - Document deletions
  - Collection drops
  - Template modifications
- **Data recorded:**
  - Action type
  - User identifier
  - Timestamp
  - IP address
  - User agent
  - Details object
- **Retention:** Permanent (until manually deleted)

---

### 10. **Session Security**
- **Session fixation prevention:** Session regeneration on login
- **Session hijacking protection:** IP and user agent binding
- **Incomplete object handling:** Automatic cleanup of corrupted BSON objects
- **Timeout:** PHP default (typically 24 minutes)

---

## 🚨 Security Best Practices

### For Administrators:

1. **Change Default Credentials**
   - Never use default MongoDB admin credentials
   - Use strong passwords (16+ characters, mixed case, numbers, symbols)

2. **Network Security**
   - Bind MongoDB to localhost or private network only
   - Use firewall rules to restrict access
   - Enable SSL/TLS for connections

3. **Access Control**
   - Create separate users with minimum required privileges
   - Use role-based access control (RBAC)
   - Regularly audit user permissions

4. **Regular Backups**
   - Use the Security tab to create backups before major changes
   - Store backups in secure, off-site location
   - Test restore procedures regularly

5. **Monitoring**
   - Review audit logs weekly
   - Check `logs/security.log` for suspicious activity
   - Set up alerts for multiple failed operations

6. **Updates**
   - Keep PHP and MongoDB drivers updated
   - Monitor security advisories
   - Apply patches promptly

---

## 🔍 Security Checklist

Before deploying to production:

- [ ] Changed default MongoDB credentials
- [ ] Configured firewall rules
- [ ] Enabled SSL/TLS
- [ ] Set up backup schedule
- [ ] Configured log rotation for `logs/security.log`
- [ ] Tested CSRF protection
- [ ] Verified rate limiting works
- [ ] Reviewed all user permissions
- [ ] Documented security procedures
- [ ] Set up monitoring/alerting

---

## 🐛 Reporting Security Issues

If you discover a security vulnerability:

1. **DO NOT** create a public issue
2. Email details to your security team
3. Include:
   - Description of vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if available)

---

## 📊 Security Testing Results

### Protections Verified:

✅ **CSRF Protection:** All dangerous operations require valid token  
✅ **Rate Limiting:** 30 req/60s enforced, violations logged  
✅ **XSS Prevention:** All inputs sanitized with `htmlspecialchars()`  
✅ **NoSQL Injection:** Query operators whitelisted, dangerous patterns blocked  
✅ **File Upload:** Size limits enforced, MIME types validated  
✅ **Audit Logging:** All critical operations tracked  

---

## 📁 Security Files Reference

| File | Purpose |
|------|---------|
| `config/security.php` | Core security functions (163 lines) |
| `includes/backup.php` | Backup utilities and audit logging |
| `logs/security.log` | Security event log (auto-created) |
| `backups/*.json.gz` | Database backup files |
| MongoDB `_audit_log` | Permanent audit trail collection |
| MongoDB `_templates` | Document templates (validated) |

---

## 🔧 Configuration

### Rate Limit Adjustment
Edit `includes/handlers.php` line 58:
```php
if (!checkRateLimit('post_action', 30, 60)) {  // 30 requests per 60 seconds
```

### CSRF Token Regeneration
Clear session or call:
```php
unset($_SESSION['csrf_token']);
generateCSRFToken(); // Creates new token
```

### Upload Size Limit
Edit `config/security.php` line 143:
```php
if ($fileSize > 5 * 1024 * 1024) {  // 5 MB limit
```

---

## 📖 Security Functions API

```php
// Generate/verify CSRF tokens
generateCSRFToken(): string
verifyCSRFToken(string $token): bool

// Input validation
sanitizeInput(mixed $input): mixed
validateJSON(string $json): bool
validateCollectionName(string $name): bool
validateFieldName(string $field): bool

// MongoDB security
sanitizeMongoQuery(array $query): array

// Rate limiting
checkRateLimit(string $action, int $limit = 50, int $period = 60): bool

// Logging
logSecurityEvent(string $event, array $details = []): void

// File uploads
validateUpload(array $file): array // ['valid' => bool, 'error' => string]

// Audit trail
auditLog(string $action, array $details, string $user = 'system'): void

// Backup
createDatabaseBackup(MongoDB\Database $db, ?string $name = null): array
listBackups(): array
```

---

## 🎯 Security Roadmap (Future Enhancements)

- [ ] Two-Factor Authentication (2FA)
- [ ] IP whitelisting
- [ ] Encrypted backups
- [ ] Advanced intrusion detection
- [ ] Role-based access control UI
- [ ] Automated security scans
- [ ] Compliance reporting (GDPR, HIPAA)
- [ ] Webhook notifications for security events

---

**Last Updated:** 2026-01-14  
**Security Framework Version:** 1.0  
**Maintained By:** Development Team
