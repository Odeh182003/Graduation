import 'package:bzu_leads/services/ApiConfig.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';

class EventFormScreen extends StatefulWidget {
  const EventFormScreen({super.key});

  @override
  _EventFormScreenState createState() => _EventFormScreenState();
}

class _EventFormScreenState extends State<EventFormScreen> {
  final TextEditingController eventTitleController = TextEditingController();
  final TextEditingController eventContentController = TextEditingController();
  DateTime? selectedDate;

  String? universityId; // Store loaded university ID

  int? maxParticipants = 10; // Default value

  @override
  void initState() {
    super.initState();
    _loadUniversityId();
  }

  Future<void> _loadUniversityId() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    setState(() {
      universityId = prefs.getString("universityID");
    });
  }

  @override
  void dispose() {
    eventTitleController.dispose();
    eventContentController.dispose();
    super.dispose();
  }

  Future<void> _selectDate(BuildContext context) async {
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: DateTime.now(),
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
    );

    if (picked != null && picked != selectedDate) {
      setState(() {
        selectedDate = picked;
      });
    }
  }

  // Function to submit the form to the PHP server
  void _submitForm() async {
    String eventTitle = eventTitleController.text.trim();
    String eventContent = eventContentController.text.trim();
    String date = selectedDate != null
        ? DateFormat('yyyy-MM-dd').format(selectedDate!)
        : "";

    // Use loaded universityId
    print("University ID: $universityId");
    print("Event Title: $eventTitle");
    print("Event Content: $eventContent");
    print("Date: $date");

    if (eventTitle.isEmpty || eventContent.isEmpty || date.isEmpty || maxParticipants == null) {
      _showErrorDialog("Please fill in all required fields.");
      return;
    }

    // Prepare JSON data for the request
    Map<String, dynamic> data = {
      'activityName': eventTitle,
      'activityHostID': universityId,
      'activityDate': DateFormat('yyyy-MM-dd').format(DateTime.now()), // Store current date as activityDate
      'expiryDate': date, // Store selected date as expiryDate
      'CONTENT': eventContent,
      'status': 'Pendding',
      'max_participants': maxParticipants, // Add this line
    };

    try {
      var response = await http.post(
        Uri.parse('${ApiConfig.baseUrl}/officials_new_event.php'), 
        headers: {'Content-Type': 'application/json'},
        body: json.encode(data),
      );

      if (response.statusCode == 200) {
        var responseData = json.decode(response.body);
        if (responseData['status'] == 'success') {
          // Clear all fields after successful addition
          setState(() {
            eventTitleController.clear();
            eventContentController.clear();
            selectedDate = null;
            maxParticipants = 10;
          });
          _showSuccessDialog(responseData['message']);
        } else {
          _showErrorDialog(responseData['message']);
        }
      } else {
        _showErrorDialog("Failed to submit event. Please try again.");
      }
    } catch (e) {
      _showErrorDialog("An error occurred: $e");
    }
  }

  void _showSuccessDialog(String message) {
    showDialog(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: const Text("Success"),
          content: Text(message),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text("OK"),
            ),
          ],
        );
      },
    );
  }

  void _showErrorDialog(String message) {
    showDialog(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: const Text("Error"),
          content: Text(message),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text("OK"),
            ),
          ],
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).colorScheme.surface,
      appBar: AppBar(
  backgroundColor: Colors.white,
  foregroundColor: Colors.green,
  elevation: 1,
  title: Row(
    children: [
      Image.network(
        ApiConfig.systemLogoUrl,
        height: 40,
        errorBuilder: (context, error, stackTrace) => Icon(Icons.broken_image),
      ),
      const SizedBox(width: 8), // Space between image and text
      const Text(
        "Create New Events",
        style: TextStyle(
          color: Colors.green, // Ensure text color matches your theme
        ),
      ),
    ],
  ),
        
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildTextField("Event Title *", eventTitleController, "Enter event title"),
            _buildTextField("Event Content *", eventContentController, "Enter event content", maxLines: 4),

            const SizedBox(height: 10),
            const Text("Date"),
            Row(
              children: [
                Text(
                  selectedDate == null
                      ? "No date selected"
                      : DateFormat('yyyy-MM-dd').format(selectedDate!),
                ),
                const SizedBox(width: 10),
                ElevatedButton(
                  onPressed: () => _selectDate(context),
                  child: const Text("Pick Date"),
                ),
              ],
            ),
            const SizedBox(height: 16),

            // Max Participants Picker
            const Text("Max Participants"),
            DropdownButtonFormField<int>(
              value: maxParticipants,
              decoration: const InputDecoration(
                border: OutlineInputBorder(),
                contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              ),
              items: List.generate(20, (index) => index + 1)
                  .map((num) => DropdownMenuItem(
                        value: num,
                        child: Text(num.toString()),
                      ))
                  .toList(),
              onChanged: (value) {
                setState(() {
                  maxParticipants = value;
                });
              },
              hint: const Text("Select max participants"),
            ),

            const SizedBox(height: 20),
            Center(
              child: ElevatedButton(
                onPressed: _submitForm,
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.green,
                  padding: const EdgeInsets.symmetric(horizontal: 50, vertical: 15),
                ),
                child: const Text("Submit", style: TextStyle(color: Colors.white)),
              ),
            ),
          ],
        ),
      ),
      /*bottomNavigationBar: BottomNavigationBar(
        items: const [
          BottomNavigationBarItem(icon: Icon(Icons.explore), label: "Explore"),
          BottomNavigationBarItem(icon: Icon(Icons.message), label: "Messaging"),
          BottomNavigationBarItem(icon: Icon(Icons.person), label: "Profile"),
        ],
      ),*/
    );
  }

  Widget _buildTextField(String label, TextEditingController controller, String hintText, {int maxLines = 1}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label),
          TextField(
            controller: controller,
            maxLines: maxLines,
            decoration: InputDecoration(
              hintText: hintText,
              border: const OutlineInputBorder(),
            ),
          ),
        ],
      ),
    );
  }
}
