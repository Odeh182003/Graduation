import 'dart:convert';
import 'package:bzu_leads/services/ApiConfig.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class SuspendStudentPage extends StatefulWidget {
  const SuspendStudentPage({Key? key}) : super(key: key);

  @override
  State<SuspendStudentPage> createState() => _SuspendStudentPageState();
}

class _SuspendStudentPageState extends State<SuspendStudentPage> {
  final TextEditingController _searchController = TextEditingController();
  List<Map<String, dynamic>> _students = [];
  bool _isLoading = false;
  String? _error;
  Map<String, dynamic>? _selectedStudent;
  DateTime? _startDate;
  DateTime? _endDate;
  bool _isSubmitting = false;
  String? _submitMessage;

  Future<void> _searchStudents() async {
    setState(() {
      _isLoading = true;
      _error = null;
      _students = [];
      _selectedStudent = null;
    });
    final search = _searchController.text.trim();
    final url = Uri.parse('${ApiConfig.baseUrl}/suspend.php?search=$search');
    try {
      final response = await http.get(url);
      final data = jsonDecode(response.body);
      if (data['success'] == true && data['students'] is List) {
        setState(() {
          _students = List<Map<String, dynamic>>.from(data['students']);
        });
      } else {
        setState(() {
          _error = "No students found.";
        });
      }
    } catch (e) {
      setState(() {
        _error = "Error searching students.";
      });
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  Future<void> _submitSuspend() async {
    if (_selectedStudent == null || _startDate == null || _endDate == null) return;
    setState(() {
      _isSubmitting = true;
      _submitMessage = null;
    });
    final prefs = await SharedPreferences.getInstance();
    final memberID = prefs.getString("universityID") ?? "";
    final url = Uri.parse('${ApiConfig.baseUrl}/suspend.php');
    final body = jsonEncode({
      "universityID": _selectedStudent!['universityID'],
      "universityAdministrationID": memberID,
      "startDate": _startDate!.toIso8601String().substring(0, 10),
      "endDate": _endDate!.toIso8601String().substring(0, 10),
    });
    try {
      final response = await http.post(url, body: body, headers: {"Content-Type": "application/json"});
      final data = jsonDecode(response.body);
      setState(() {
        _submitMessage = data['message'] ?? "Unknown response";
      });
      if (data['success'] == true) {
        _selectedStudent = null;
        _startDate = null;
        _endDate = null;
        _searchController.clear();
        _students = [];
      }
    } catch (e) {
      setState(() {
        _submitMessage = "Error suspending student.";
      });
    } finally {
      setState(() {
        _isSubmitting = false;
      });
    }
  }

  Future<void> _pickDate(BuildContext context, bool isStart) async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: now,
      firstDate: DateTime(now.year - 1),
      lastDate: DateTime(now.year + 2),
    );
    if (picked != null) {
      setState(() {
        if (isStart) {
          _startDate = picked;
        } else {
          _endDate = picked;
        }
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.grey[100],
      appBar: AppBar(
        backgroundColor: Colors.white,
        foregroundColor: Colors.green,
        // elevation: 1,
        title: Row(
          children: [
            Flexible(
              child: Row(
                children: [
                  Image.network(
                    ApiConfig.systemLogoUrl,
                    height: 40,
                    errorBuilder: (context, error, stackTrace) => Icon(Icons.broken_image),
                  ),
                  const SizedBox(width: 8),
                  const Text(
                    "Suspend Student",
                    style: TextStyle(
                      color: Colors.green,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            TextField(
              controller: _searchController,
              decoration: InputDecoration(
                labelText: "Search by University ID or Name",
                suffixIcon: IconButton(
                  icon: const Icon(Icons.search),
                  onPressed: _isLoading ? null : _searchStudents,
                ),
              ),
              onSubmitted: (_) => _searchStudents(),
            ),
            const SizedBox(height: 16),
            if (_isLoading) const CircularProgressIndicator(),
            if (_error != null) Text(_error!, style: const TextStyle(color: Colors.red)),
            if (_students.isNotEmpty)
              Expanded(
                child: ListView.builder(
                  itemCount: _students.length,
                  itemBuilder: (context, index) {
                    final student = _students[index];
                    return ListTile(
                      title: Text(student['username']),
                      subtitle: Text("University ID: ${student['universityID']}"),
                      trailing: _selectedStudent != null &&
                              _selectedStudent!['universityID'] == student['universityID']
                          ? const Icon(Icons.check_circle, color: Colors.green)
                          : null,
                      onTap: () {
                        setState(() {
                          _selectedStudent = student;
                        });
                      },
                    );
                  },
                ),
              ),
            if (_selectedStudent != null)
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Divider(),
                  Text("Selected Student: ${_selectedStudent!['username']} (${_selectedStudent!['universityID']})"),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: InkWell(
                          onTap: () => _pickDate(context, true),
                          child: InputDecorator(
                            decoration: const InputDecoration(labelText: "Start Date"),
                            child: Text(_startDate == null
                                ? "Select"
                                : _startDate!.toIso8601String().substring(0, 10)),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: InkWell(
                          onTap: () => _pickDate(context, false),
                          child: InputDecorator(
                            decoration: const InputDecoration(labelText: "End Date"),
                            child: Text(_endDate == null
                                ? "Select"
                                : _endDate!.toIso8601String().substring(0, 10)),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  ElevatedButton.icon(
                    icon: _isSubmitting
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                          )
                        : const Icon(Icons.block),
                    label: const Text("Suspend Student"),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.red,
                      foregroundColor: Colors.white,
                    ),
                    onPressed: _isSubmitting ? null : _submitSuspend,
                  ),
                  if (_submitMessage != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Text(
                        _submitMessage!,
                        style: TextStyle(
                          color: _submitMessage!.toLowerCase().contains("success")
                              ? Colors.green
                              : Colors.red,
                        ),
                      ),
                    ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}
