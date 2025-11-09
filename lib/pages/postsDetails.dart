//import 'package:bzu_leads/pages/profile_page.dart';
//import 'package:bzu_leads/pages/settingsPage.dart';
import 'package:bzu_leads/services/ApiConfig.dart';
import 'package:bzu_leads/services/comments.dart';
import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:open_file/open_file.dart';
import 'package:path_provider/path_provider.dart';

class Postsdetails extends StatefulWidget {
  final int postID;

  const Postsdetails({super.key, required this.postID});

  @override
  _PostsDetailsPageState createState() => _PostsDetailsPageState();
}

class _PostsDetailsPageState extends State<Postsdetails> {
  PlatformFile? _selectedCommentFile;
TextEditingController commentController = TextEditingController();

  Map<String, dynamic>? post;
  late Future<List<Comment>> _commentsFuture;

  @override
  void initState() {
    super.initState();
    fetchPostById();
    _commentsFuture = fetchComments(widget.postID);
  }

  Future<void> fetchPostById() async {
    try {
      final response = await http.get(Uri.parse(
          '${ApiConfig.baseUrl}/postsDetails.php?postID=${widget.postID}'));

      if (response.statusCode == 200) {
        final data = json.decode(response.body);

        // Check for error in response
        if (data is Map && data.containsKey('error')) {
          if (mounted) {
            setState(() {
              post = null;
            });
          }
          // Optionally show a snackbar or dialog with error message
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(data['error'].toString())),
          );
          return;
        }

        if (data.containsKey('post') && data['post'] != null) {
          if (mounted) {
            setState(() {
              // Convert all int values to String to avoid type errors in widgets
              post = (data['post'] as Map<String, dynamic>).map<String, dynamic>((key, value) {
                if (value is int) {
                  return MapEntry(key, value.toString());
                }
                return MapEntry(key, value);
              });
            });
          }
        } else {
          if (mounted) {
            setState(() {
              post = null;
            });
          }
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text("Post not found.")),
          );
        }
      } else {
        if (mounted) {
          setState(() {
            post = null;
          });
        }
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text("Failed to load post details.")),
        );
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          post = null;
        });
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text("Error loading post details.")),
      );
    }
  }

Future<void> submitComment() async {
  final prefs = await SharedPreferences.getInstance();
  final userID = prefs.getString("universityID");

  var uri = Uri.parse("${ApiConfig.baseUrl}/insertComment.php");

  var request = http.MultipartRequest('POST', uri);
  request.fields['postID'] = widget.postID.toString();
  request.fields['commentCreatorID'] = userID!;
  request.fields['commentText'] = commentController.text;

  if (_selectedCommentFile != null && _selectedCommentFile!.path != null) {
    request.files.add(await http.MultipartFile.fromPath(
      'attachment',
      _selectedCommentFile!.path!,
    ));
  }

  var response = await request.send();
  var responseBody = await response.stream.bytesToString();

  if (response.statusCode == 200) {
    print("Comment submitted: $responseBody");
    setState(() {
      commentController.clear();
      _selectedCommentFile = null;
      _commentsFuture = fetchComments(widget.postID);
    });
  } else {
    print("Submission failed: $responseBody");
  }
}

  Future<List<Comment>> fetchComments(int postID) async {
    final url = Uri.parse("${ApiConfig.baseUrl}/getComments.php?postID=$postID");
    final response = await http.get(url);

    if (response.statusCode == 200) {
      final data = json.decode(response.body);

      if (data['comments'] is List) {
        return (data['comments'] as List)
            .map((c) => Comment.fromJson(c))
            .toList();
      }
    }
    return [];
  }

  // Helper to detect Arabic text
  bool _isArabic(String? text) {
    if (text == null) return false;
    final arabicRegExp = RegExp(r'[\u0600-\u06FF]');
    return arabicRegExp.hasMatch(text);
  }

  @override
  Widget build(BuildContext context) {
    // Helper for file download/open
    Future<void> downloadFile(String url, String fileName) async {
      try {
        Directory? downloadsDir;
        try {
          downloadsDir = await getDownloadsDirectory();
        } catch (e) {
          downloadsDir = await getApplicationDocumentsDirectory();
        }
        if (downloadsDir == null) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text("Cannot access downloads directory.")),
          );
          return;
        }
        final savePath = "${downloadsDir.path}/$fileName";
        final response = await http.get(Uri.parse(url));
        if (response.statusCode == 200) {
          final file = File(savePath);
          await file.writeAsBytes(response.bodyBytes);
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text("Downloaded to $savePath")),
          );
          try {
            await OpenFile.open(savePath);
          } catch (_) {}
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text("Failed to download file (status ${response.statusCode}).")),
          );
        }
      } catch (e) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text("Error downloading file: $e")),
        );
      }
    }

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
              "Posts Details",
              style: TextStyle(
                color: Colors.green, // Ensure text color matches your theme
              ),
            ),
          ],
        ),
       
      ),
      body: post == null
          ? Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // --- Attachments section ---
                    if (post != null && post!["media"] != null && post!["media"] is List && (post!["media"] as List).isNotEmpty)
                      ...() {
                        final List<dynamic> mediaList = post!["media"];
                        final imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
                        final List<String> images = [];
                        final List<String> files = [];
                        for (var item in mediaList) {
                          if (item is String) {
                            final ext = item.split('.').last.toLowerCase();
                            if (imageExtensions.contains(ext)) {
                              images.add(item);
                            } else {
                              files.add(item);
                            }
                          }
                        }
                        return [
                          if (images.isNotEmpty)
                            Center(
                              child: ClipRRect(
                                borderRadius: BorderRadius.circular(16),
                                child: _buildMediaCarousel(images),
                              ),
                            )
                          else
                            Center(
                              child: Container(
                                height: 250,
                                color: Colors.grey[300],
                                child: Center(
                                  child: Icon(Icons.image, size: 100, color: Colors.grey[600]),
                                ),
                              ),
                            ),
                          if (files.isNotEmpty)
                            Padding(
                              padding: const EdgeInsets.only(top: 12.0, bottom: 12.0),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Text("Attachments:", style: TextStyle(fontWeight: FontWeight.w600)),
                                  ...files.map((file) {
                                    final fileName = file.split('/').last;
                                    final fileUrl = "${ApiConfig.baseUrl}/$file";
                                    IconData icon;
                                    final ext = fileName.split('.').last.toLowerCase();
                                    if (ext == 'pdf') {
                                      icon = Icons.picture_as_pdf;
                                    } else if (ext == 'doc' || ext == 'docx') {
                                      icon = Icons.description;
                                    } else {
                                      icon = Icons.attach_file;
                                    }
                                    return ListTile(
                                      leading: Icon(icon, color: Colors.green),
                                      title: Text(fileName, overflow: TextOverflow.ellipsis),
                                      onTap: () async {
                                        await downloadFile(fileUrl, fileName);
                                      },
                                      trailing: Icon(Icons.download, color: Colors.green),
                                    );
                                  }).toList(),
                                ],
                              ),
                            ),
                        ];
                      }(),
                    if (post == null || post!["media"] == null || (post!["media"] is List && (post!["media"] as List).isEmpty))
                      Center(
                        child: Container(
                          height: 250,
                          color: Colors.grey[300],
                          child: Center(
                            child: Icon(Icons.image, size: 100, color: Colors.grey[600]),
                          ),
                        ),
                      ),
                    const SizedBox(height: 20),
                    Directionality(
                      textDirection: _isArabic(post!["posttitle"]) ? TextDirection.rtl : TextDirection.ltr,
                      child: Text(
                        post!["posttitle"] ?? 'No Title',
                        style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold),
                      ),
                    ),
                    const SizedBox(height: 10),
                    Directionality(
                      textDirection: _isArabic(post!["username"]) ? TextDirection.rtl : TextDirection.ltr,
                      child: Text(
                        "By: ${post!["username"]} (${post!["POSTCREATORID"]})",
                        style: TextStyle(color: Colors.black),
                      ),
                    ),
                    const SizedBox(height: 10),
                    Directionality(
                      textDirection: _isArabic(post!["CONTENT"]) ? TextDirection.rtl : TextDirection.ltr,
                      child: Text(
                        post!["CONTENT"] ?? '',
                        style: Theme.of(context).textTheme.bodyLarge,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Divider(),
                    Row(
                      children: [
                        Icon(Icons.check_circle, color: Colors.green[700]),
                        const SizedBox(width: 8),
                        Expanded(child: Text("Status: ${post!["STATUS"]}")),
                      ],
                    ),
                    const SizedBox(height: 8),
                    // Conditionally show "Approved By"
                    if (post!["APPROVERNAME"] != null && post!["APPROVERNAME"].toString().isNotEmpty)
                      Text("Approved By: ${post!["APPROVERNAME"]}${post!["REVIEWEDBY"] != null && post!["REVIEWEDBY"].toString().isNotEmpty ? ', ${post!["REVIEWEDBY"]}' : ''}"),
                    // Conditionally show "Email"
                    if (post!["email"] != null && post!["email"].toString().isNotEmpty)
                      Text("Email: ${post!["email"]}"),
                    const SizedBox(height: 8),
                    Text("Published: ${post!["DATECREATED"]}"),
                    // Conditionally show "Approved"
                    if (post!["APPROVALDATE"] != null && post!["APPROVALDATE"].toString().isNotEmpty)
                      Text("Approved: ${post!["APPROVALDATE"]}"),
                    const SizedBox(height: 20),
                    Center(
                      child: ElevatedButton.icon(
                        onPressed: () async {
                          SharedPreferences prefs = await SharedPreferences.getInstance();
                          String? userID = prefs.getString("universityID");

                          if (userID == null) {
                            ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(content: Text("Please login to comment.")));
                            return;
                          }

                          //String commentText = '';
showDialog(
  context: context,
  builder: (context) {
    return StatefulBuilder(
      builder: (context, setState) {
        return AlertDialog(
          title: const Text("Add Comment"),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: commentController,
                  maxLines: 3,
                  decoration: const InputDecoration(labelText: "Comment"),
                ),
                const SizedBox(height: 10),
                ElevatedButton(
                  onPressed: () async {
                    FilePickerResult? result = await FilePicker.platform.pickFiles();
                    if (result != null) {
                      setState(() {
                        _selectedCommentFile = result.files.first;
                      });
                    }
                  },
                  child: Text(_selectedCommentFile == null
                      ? "Attach File"
                      : "Attached: ${_selectedCommentFile!.name}"),
                ),
                if (_selectedCommentFile != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 10),
                    child: Column(
                      children: [
                        Text(
                          "Selected Attachment:",
                          style: TextStyle(fontWeight: FontWeight.w600, color: Colors.green[800]),
                        ),
                        const SizedBox(height: 6),
                        Row(
                          children: [
                            Icon(Icons.attach_file, color: Colors.green),
                            Expanded(
                              child: Text(
                                _selectedCommentFile!.name,
                                style: const TextStyle(fontSize: 14),
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text("Cancel"),
            ),
            TextButton(
              onPressed: () {
                Navigator.pop(context);
                submitComment();
              },
              child: const Text("Submit"),
            ),
          ],
        );
      },
    );
  },
);

                        },
                        icon: Icon(
                          Icons.comment,
                          color: Colors.white,  // Set the color to white
),

                        label: Text(
                                "Comment",
                                 style: TextStyle(color: Colors.white), 
),

                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.green,
                          shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12)),
                        ),
                      ),
                    ),
                    const SizedBox(height: 30),
                    Text("Comments",
                        style: Theme.of(context)
                            .textTheme
                            .titleLarge
                            ?.copyWith(fontWeight: FontWeight.bold)),
                    const SizedBox(height: 10),
                    FutureBuilder<List<Comment>>(
                      future: _commentsFuture,
                      builder: (context, snapshot) {
                        if (snapshot.connectionState == ConnectionState.waiting) {
                          return Center(child: CircularProgressIndicator());
                        } else if (snapshot.hasError || !snapshot.hasData || snapshot.data!.isEmpty) {
                          return Center(child: Text("No comments yet."));
                        }
                        final comments = snapshot.data!;
                        return ListView.builder(
                          shrinkWrap: true,
                          physics: NeverScrollableScrollPhysics(),
                          itemCount: comments.length,
                          itemBuilder: (context, index) {
                            final comment = comments[index];
                            return Card(
  margin: const EdgeInsets.symmetric(vertical: 6),
  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
  child: Padding(
    padding: const EdgeInsets.all(10.0),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Directionality(
          textDirection: _isArabic(comment.text) ? TextDirection.rtl : TextDirection.ltr,
          child: Text(comment.text),
        ),
        const SizedBox(height: 4),
        Directionality(
          textDirection: _isArabic(comment.username) ? TextDirection.rtl : TextDirection.ltr,
          child: Text(
            "By ${comment.username}, ${comment.creatorId} at ${comment.timestamp}",
            style: const TextStyle(color: Colors.grey),
          ),
        ),
        if (comment.attachment != null && comment.attachment!.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 8.0),
            child: TextButton.icon(
              onPressed: () async {
                final fileName = comment.attachment!.split("/").last;
                final fileUrl = "${ApiConfig.baseUrl}/${comment.attachment}";
                await downloadFile(fileUrl, fileName);
              },
              icon: const Icon(Icons.attach_file, color: Colors.green),
              label: Text(
                "View Attachment",
                style: TextStyle(color: Colors.green[800]),
              ),
            ),
          ),
      ],
    ),
  ),
);

                          },
                        );
                      },
                    ),
                  ],
                ),
              ),
            ),
    );
  }

  Widget _buildMediaCarousel(dynamic media) {
    // Accepts List from backend, fallback to empty list if not a List
    final List<dynamic> mediaList = (media is List) ? media : [];
    int currentIndex = 0;

    return StatefulBuilder(
      builder: (context, setState) {
        return Column(
          children: [
            SizedBox(
              height: 400,
              child: PageView.builder(
                itemCount: mediaList.length,
                onPageChanged: (index) {
                  setState(() {
                    currentIndex = index;
                  });
                },
                itemBuilder: (context, index) {
                  final url = "${ApiConfig.baseUrl}/${mediaList[index]}";
                  return ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: Image.network(
                      url,
                      fit: BoxFit.contain,
                      loadingBuilder: (context, child, loadingProgress) {
                        if (loadingProgress == null) return child;
                        return Center(
                          child: CircularProgressIndicator(
                            value: loadingProgress.expectedTotalBytes != null
                                ? loadingProgress.cumulativeBytesLoaded / (loadingProgress.expectedTotalBytes ?? 1)
                                : null,
                          ),
                        );
                      },
                      errorBuilder: (context, error, stackTrace) {
                        return Container(
                          color: Colors.grey[300],
                          child: Icon(Icons.broken_image, size: 100, color: Colors.grey[600]),
                        );
                      },
                    ),
                  );
                },
              ),
            ),
            if (mediaList.length > 1)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: List.generate(
                    mediaList.length,
                    (index) => Container(
                      margin: const EdgeInsets.symmetric(horizontal: 4),
                      width: 8,
                      height: 8,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: currentIndex == index ? Colors.green : Colors.grey,
                      ),
                    ),
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}