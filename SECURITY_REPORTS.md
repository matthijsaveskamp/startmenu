# Security Vulnerability Report - Startpagina PHP

This document outlines the security vulnerabilities identified in the Startpagina PHP application (v1.00.00).

## 1. Cross-Site Scripting (XSS) via `javascript:` URIs
**Vulnerability Type:** Stored XSS
**Location:** `admin.php` (Link addition/editing), `index.php` (Link display)
**Description:** The application does not validate the protocol of user-provided URLs. An attacker with administrative access (or by tricking an admin into importing a malicious JSON/ZIP) can add a link with a `javascript:` URI. When a user clicks this link on the homepage, the script executes in the context of the site.
**Fix:** Implement a URL validation function that only allows safe protocols (http, https, mailto, tel).

## 2. Insecure Direct Object Reference / Information Disclosure (JSON files)
**Vulnerability Type:** Information Disclosure
**Location:** Root directory (`links.json`, `settings.json`, `categories.json`, `trash.json`)
**Description:** Sensitive data files are stored in the web root without any access protection. Anyone who knows the filenames can download the entire database of links, categories, and settings.
**Fix:** Move data files to a protected `data/` directory and use `.htaccess` to deny all web access.

## 3. Hardcoded Credentials
**Vulnerability Type:** Insecure Configuration
**Location:** `config.php`
**Description:** The application comes with a default plain-text password (`veranderdit`) hardcoded in the configuration file. This password is used for both comparison and re-hashing on every request.
**Fix:** Remove the plain-text password and use only a secure hash.

## 4. XSS via SVG Uploads
**Vulnerability Type:** Stored XSS
**Location:** `admin.php` (Icon/Avatar upload)
**Description:** The application allows the upload of SVG files. SVG files can contain embedded `<script>` tags. While they may not execute when used in an `<img>` tag, they can execute if a user navigates directly to the SVG file's URL.
**Fix:** Remove `svg` from the list of allowed extensions or implement SVG sanitization.

## 5. Information Disclosure (Debug Logs)
**Vulnerability Type:** Information Disclosure
**Location:** `assets/uploads/upload-debug.log`
**Description:** Upload errors, which may include IP addresses and file names, are logged to a publicly accessible file.
**Fix:** Move the log file to a protected directory.

## 6. Lack of Security Headers
**Vulnerability Type:** Insecure Configuration
**Location:** All pages
**Description:** The application does not set standard security headers such as `X-Frame-Options`, `X-Content-Type-Options`, or `Content-Security-Policy`.
**Fix:** Add standard security headers to all responses.

## 7. Insecure Session Management
**Vulnerability Type:** Insecure Configuration
**Location:** `admin.php`
**Description:** Sessions are started without the `HttpOnly` flag, making session cookies accessible via JavaScript, which increases the risk of session hijacking via XSS.
**Fix:** Set `session.cookie_httponly` to true.
