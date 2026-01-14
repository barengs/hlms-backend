# Hybrid LMS API Documentation

Selamat datang di dokumentasi API Hybrid LMS. Dokumentasi ini mencakup semua endpoint yang tersedia untuk Admin, Instruktur, dan Siswa.

## Base URL

Semua request API harus diarahkan ke URL dasar berikut:

`http://localhost:8000/api/v1` (Local)
`https://api.domain.com/api/v1` (Production)

## Authentication

API ini menggunakan **Laravel Sanctum** untuk otentikasi. Anda harus mengirimkan token akses dalam header `Authorization` untuk setiap request yang dilindungi.

**Format Header:**

```http
Authorization: Bearer <your-access-token>
Accept: application/json
```

---

## Panduan Integrasi Web (Javascript)

Berikut adalah contoh penggunaan menggunakan **Fetch API** dan **Axios** untuk aplikasi berbasis Javascript (React, Vue, dll).

### 1. Login & Simpan Token

```javascript
// Menggunakan Axios
async function login(email, password) {
    try {
        const response = await axios.post(
            "http://localhost:8000/api/v1/auth/login",
            {
                email: email,
                password: password,
            }
        );

        // Simpan token di localStorage
        const token = response.data.data.access_token;
        localStorage.setItem("auth_token", token);

        return token;
    } catch (error) {
        console.error("Login failed:", error.response.data.message);
    }
}
```

### 2. Request Data (Authenticated)

```javascript
async function getStudentDashboard() {
    const token = localStorage.getItem("auth_token");

    try {
        const response = await axios.get(
            "http://localhost:8000/api/v1/student/dashboard",
            {
                headers: {
                    Authorization: `Bearer ${token}`,
                    Accept: "application/json",
                },
            }
        );

        console.log("Stats:", response.data.data.stats);
    } catch (error) {
        if (error.response.status === 401) {
            // Token expired, redirect to login
        }
    }
}
```

---

## Panduan Integrasi Mobile (Flutter)

Berikut adalah contoh penggunaan menggunakan package `http` di Flutter.

### 1. Setup Service

Buat sebuah class `ApiService` untuk menangani request.

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class ApiService {
  final String baseUrl = "http://10.0.2.2:8000/api/v1"; // 10.0.2.2 for Android Emulator

  // Helper untuk mendapatkan headers
  Future<Map<String, String>> _getHeaders() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString('auth_token');
    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }

  // Login Method
  Future<bool> login(String email, String password) async {
    final url = Uri.parse('$baseUrl/auth/login');
    final response = await http.post(
      url,
      body: jsonEncode({'email': email, 'password': password}),
      headers: {'Content-Type': 'application/json'},
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      final token = data['data']['access_token'];

      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('auth_token', token);
      return true;
    }
    return false;
  }
}
```

### 2. Mengambil Data (Contoh: Dashboard Siswa)

```dart
  Future<Map<String, dynamic>?> getStudentDashboard() async {
    final url = Uri.parse('$baseUrl/student/dashboard');
    final headers = await _getHeaders();

    final response = await http.get(url, headers: headers);

    if (response.statusCode == 200) {
      final json = jsonDecode(response.body);
      return json['data']; // Mengembalikan object data dashboard
    } else {
      print('Error: ${response.statusCode}');
      return null;
    }
  }
```

### 3. Upload Tugas (Multipart Request)

```dart
  Future<void> submitAssignment(String assignmentId, String filePath) async {
    final url = Uri.parse('$baseUrl/student/assignments/$assignmentId/submit');
    final headers = await _getHeaders(); // Ambil token saja, Content-Type akan otomatis multipart

    var request = http.MultipartRequest('POST', url);
    request.headers.addAll({
      'Authorization': headers['Authorization']!,
      'Accept': 'application/json',
    });

    request.files.add(await http.MultipartFile.fromPath('file', filePath));

    var response = await request.send();

    if (response.statusCode == 200) {
      print('Upload berhasil');
    }
  }
```
