import 'dart:convert';
import 'dart:io';
import 'package:bzu_leads/services/ApiConfig.dart';
import 'package:bzu_leads/services/user_session.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:http/http.dart' as http;
import 'package:file_picker/file_picker.dart';

class Studentcreateposts extends StatefulWidget {
  const Studentcreateposts({super.key});

  @override
  _Studentcreateposts createState() => _Studentcreateposts();
}

class _Studentcreateposts extends State<Studentcreateposts> {
  final TextEditingController _titleController = TextEditingController();
  final TextEditingController _contentController = TextEditingController();
  String _postType = 'public';
  List<File> _selectedImages = []; // Allow multiple images
  List<File> _selectedFiles = []; // <-- Add this for attachments
  String? _userId;
  int? _facultyId;
  int? _selectedApproverId;
  List<Map<String, dynamic>> _approvers = [];

  @override
  void initState() {
    super.initState();
    _initUserAndFaculty();
  }

  Future<void> _initUserAndFaculty() async {
    _userId = await UserHelper.getUniversityID();
    if (_userId != null) {
      _facultyId = await UserHelper.getFacultyID(_userId!);
      _fetchApprovers(); // Fetch default (public) approvers on load
    }
  }

  Future<void> _fetchApprovers() async {
    if (_facultyId == null || _userId == null) return;

    Uri uri;
    if (kIsWeb) {
      uri = Uri.parse("${ApiConfig.baseUrl}/getApprovers.php");
    } else {
      uri = Uri.parse("${ApiConfig.baseUrl}/getApprovers.php");
    }
    uri = Uri.parse(uri.toString()).replace(queryParameters: {
      "postType": _postType,
      "facultyID": _facultyId.toString(),
      "studentID": _userId!
    });

    try {
      final response = await http.get(uri);
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data["success"] == true) {
          setState(() {
            _approvers = List<Map<String, dynamic>>.from(data["data"]);
            _selectedApproverId = null; // Reset selected approver
          });
        } else {
          print("Fetch failed: ${data['message']}");
        }
      } else {
        print("Failed to fetch approvers: ${response.statusCode}");
      }
    } catch (e) {
      print("Error loading approvers: $e");
    }
  }

  Future<void> _pickImages() async {
    final ImagePicker picker = ImagePicker();
    final List<XFile>? pickedFiles = await picker.pickMultiImage();
    if (pickedFiles != null) {
      setState(() {
        _selectedImages = pickedFiles.map((file) => File(file.path)).toList();
      });
    }
  }

  Future<void> _pickFiles() async {
    try {
      FilePickerResult? result = await FilePicker.platform.pickFiles(
        allowMultiple: true,
        withData: false,
      );
      if (result != null) {
        setState(() {
          _selectedFiles = result.paths
              .where((path) => path != null)
              .map((path) => File(path!))
              .toList();
        });
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text("Error picking files: $e")),
      );
    }
  }

  Future<void> _submitPost() async {
    if (_titleController.text.isEmpty || _contentController.text.isEmpty || _userId == null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text("Please fill all required fields.")));
      return;
    }

    if (_selectedApproverId == null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text("Please select an approver.")));
      return;
    }

    final url = "${ApiConfig.baseUrl}/studentPost.php";

    var request = http.MultipartRequest('POST', Uri.parse(url));
    request.fields["POSTCREATORID"] = _userId!;
    request.fields["posttitle"] = _titleController.text;
    request.fields["CONTENT"] = _contentController.text;
    request.fields["REVIEWEDBY"] = _selectedApproverId.toString();
    request.fields["postType"] = _postType;

    if (_postType == "private") {
      if (_facultyId == null) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text("Faculty ID not found. Please re-login.")));
        return;
      }
      request.fields["facultyID"] = _facultyId.toString();
    }

    // Add images as media[]
    for (var image in _selectedImages) {
      request.files.add(await http.MultipartFile.fromPath('media[]', image.path));
    }

    // Add attachments as media[]
    for (var file in _selectedFiles) {
      request.files.add(await http.MultipartFile.fromPath('media[]', file.path));
    }

    try {
      var streamedResponse = await request.send();
      var response = await http.Response.fromStream(streamedResponse);

      print("Backend response: ${response.body}");

      final responseData = jsonDecode(response.body);
      if (response.statusCode == 200 && responseData["success"] == true) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text("Post submitted successfully!")));
        _titleController.clear();
        _contentController.clear();
        setState(() {
          _selectedImages = [];
          _selectedFiles = [];
          _selectedApproverId = null;
        });
      } else {
        print("Submission failed: ${responseData['message']}");
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text("Failed: ${responseData['message']}")));
      }
    } catch (e) {
      print("Error submitting post: $e");
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text("Error submitting post.")));
    }
  }

  void _onPostTypeChanged(String? newValue) {
    if (newValue != null && newValue != _postType) {
      setState(() {
        _postType = newValue;
        _selectedApproverId = null;
        _approvers = [];
      });
      _fetchApprovers(); // Re-fetch approvers based on new post type
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
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
              "Student Create Post",
              style: TextStyle(
                color: Colors.green, // Ensure text color matches your theme
              ),
            ),
          ],
        ),
      ),
      body: SingleChildScrollView(
        padding: EdgeInsets.all(16),
        child: Card(
          elevation: 4,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text("Create a New Post", style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Colors.green)),
                SizedBox(height: 24),
                TextField(
                  controller: _titleController,
                  decoration: InputDecoration(
                    labelText: "Post Title",
                    prefixIcon: Icon(Icons.title),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
                SizedBox(height: 20),
                TextField(
                  controller: _contentController,
                  maxLines: 6,
                  decoration: InputDecoration(
                    labelText: "Content",
                    alignLabelWithHint: true,
                    prefixIcon: Icon(Icons.description),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
                SizedBox(height: 20),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text("Images", style: TextStyle(fontWeight: FontWeight.w600)),
                    ElevatedButton.icon(
                      onPressed: _pickImages,
                      icon: Icon(Icons.image, color: Colors.white),
                      label: Text("Pick Images", style: TextStyle(color: Colors.white)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.green,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        elevation: 0,
                        padding: EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                      ),
                    ),
                  ],
                ),
                SizedBox(height: 8),
                if (_selectedImages.isNotEmpty)
                  Container(
                    height: 110,
                    margin: EdgeInsets.only(top: 10, bottom: 10),
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: _selectedImages.length,
                      separatorBuilder: (_, __) => SizedBox(width: 10),
                      itemBuilder: (context, idx) {
                        final image = _selectedImages[idx];
                        return Stack(
                          children: [
                            ClipRRect(
                              borderRadius: BorderRadius.circular(10),
                              child: Image.file(image, height: 100, width: 100, fit: BoxFit.cover),
                            ),
                            Positioned(
                              top: 2,
                              right: 2,
                              child: GestureDetector(
                                onTap: () {
                                  setState(() {
                                    _selectedImages.removeAt(idx);
                                  });
                                },
                                child: CircleAvatar(
                                  radius: 14,
                                  backgroundColor: Colors.white,
                                  child: Icon(Icons.cancel, color: Colors.red, size: 18),
                                ),
                              ),
                            ),
                          ],
                        );
                      },
                    ),
                  )
                else
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 8.0),
                    child: Text("No images selected", style: TextStyle(color: Colors.grey)),
                  ),
                // --- Attachments section ---
                SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text("Attachments", style: TextStyle(fontWeight: FontWeight.w600)),
                    ElevatedButton.icon(
                      onPressed: _pickFiles, // Uncomment and implement _pickFiles with file_picker
                      icon: Icon(Icons.attach_file, color: Colors.white),
                      label: Text("Pick Files", style: TextStyle(color: Colors.white)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.green,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        elevation: 0,
                        padding: EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                      ),
                    ),
                  ],
                ),
                SizedBox(height: 8),
                if (_selectedFiles.isNotEmpty)
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: _selectedFiles.map((file) => ListTile(
                      leading: Icon(Icons.insert_drive_file, color: Colors.green),
                      title: Text(file.path.split('/').last),
                      trailing: IconButton(
                        icon: Icon(Icons.cancel, color: Colors.red),
                        onPressed: () {
                          setState(() {
                            _selectedFiles.remove(file);
                          });
                        },
                      ),
                    )).toList(),
                  )
                else
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 8.0),
                    child: Text("No attachments selected", style: TextStyle(color: Colors.grey)),
                  ),
                Divider(height: 32),
               
                Text("Post Type", style: TextStyle(fontWeight: FontWeight.w600)),
                SizedBox(height: 8),
                Row(
                  children: [
                    ChoiceChip(
                      label: Text("Public"),
                      selected: _postType == "public",
                      selectedColor: Colors.green.shade100,
                      onSelected: (selected) {
                        if (selected) _onPostTypeChanged("public");
                      },
                      labelStyle: TextStyle(color: _postType == "public" ? Colors.green : Colors.black),
                    ),
                    SizedBox(width: 12),
                    ChoiceChip(
                      label: Text("Private"),
                      selected: _postType == "private",
                      selectedColor: Colors.green.shade100,
                      onSelected: (selected) {
                        if (selected) _onPostTypeChanged("private");
                      },
                      labelStyle: TextStyle(color: _postType == "private" ? Colors.green : Colors.black),
                    ),
                  ],
                ),
                SizedBox(height: 24),
                Text("Select Approver", style: TextStyle(fontWeight: FontWeight.w600)),
                SizedBox(height: 8),
                _approvers.isEmpty
                    ? Center(child: CircularProgressIndicator())
                    : DropdownButtonFormField<int>(
                        value: _selectedApproverId,
                        decoration: InputDecoration(
                          labelText: "Approver",
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                          prefixIcon: Icon(Icons.person_search),
                        ),
                        items: _approvers.map((approver) {
                          return DropdownMenuItem<int>(
                            value: approver["id"],
                            child: Text(approver["name"]),
                          );
                        }).toList(),
                        onChanged: (value) => setState(() => _selectedApproverId = value),
                      ),
                SizedBox(height: 32),
                Center(
                  child: SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: _submitPost,
                      icon: Icon(Icons.send, color: Colors.white),
                      label: Text("Submit Post", style: TextStyle(fontSize: 18, color: Colors.white)),
                      style: ElevatedButton.styleFrom(
                        minimumSize: Size.fromHeight(50),
                        backgroundColor: Colors.green,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        elevation: 2,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
