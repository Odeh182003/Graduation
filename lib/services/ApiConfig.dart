import 'dart:convert';

import 'package:http/http.dart' as http;

class ApiConfig {
  static String? baseUrl;
  static String? systemLogoPath;
  // Initialize it once during app startup
  static Future<void> loadBaseUrl() async {
    final response = await http.get(
      Uri.parse(
        'http://192.168.1.48/public_html/FlutterGrad/getSystemConfig.php',
      ),
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      if (data['success'] && data['data'] != null) {
        final ip = data['data']['systemIpAddress'];
        baseUrl = 'http://$ip/public_html/FlutterGrad';
        systemLogoPath = data['data']['systemLogo'];
      } else {
        throw Exception("Invalid config response");
      }
    } else {
      throw Exception("Failed to load system config");
    }
  }

  static String get getbaseUrl {
    if (baseUrl == null) {
      throw Exception(
        "Base URL not initialized. Call ApiConfig.loadBaseUrl() first.",
      );
    }
    return baseUrl!;
  }

  static String get systemLogoUrl {
    if (baseUrl == null || systemLogoPath == null) {
      throw Exception("Logo URL not initialized.");
    }
    return "$baseUrl/$systemLogoPath";
  }
}
