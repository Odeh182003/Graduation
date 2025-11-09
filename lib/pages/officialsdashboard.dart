import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:bzu_leads/pages/EditActivity.dart';
import 'package:bzu_leads/pages/OfficialNotification.dart';
import 'package:bzu_leads/pages/RejectedPostsPage.dart';
import 'package:bzu_leads/pages/StatisticsDashboard.dart';
import 'package:bzu_leads/pages/academicRoom.dart';
//import 'package:bzu_leads/pages/calender.dart';
import 'package:bzu_leads/pages/chattingGroup_page.dart';
import 'package:bzu_leads/pages/chatting_page.dart';
import 'package:bzu_leads/pages/createCostumeGroup.dart';
import 'package:bzu_leads/pages/createEventOfficials.dart';
import 'package:bzu_leads/pages/PostFormScreen.dart';
import 'package:bzu_leads/pages/participate.dart';
import 'package:bzu_leads/pages/participators.dart';
import 'package:bzu_leads/pages/postsDetails.dart';
import 'package:bzu_leads/pages/private_chats.dart';
import 'package:bzu_leads/pages/private_posts.dart';
import 'package:bzu_leads/pages/profile_page.dart';
import 'package:bzu_leads/pages/registration.dart';
import 'package:bzu_leads/pages/settingsPage.dart';
import 'package:bzu_leads/services/ApiConfig.dart';
import 'package:bzu_leads/services/editPosts.dart';
import 'package:bzu_leads/services/group_service.dart';
import 'package:bzu_leads/services/post_service.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:open_file/open_file.dart';
import 'package:path_provider/path_provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shimmer/shimmer.dart';
import 'package:bzu_leads/pages/suspend.dart';
class Officialsdashboard extends StatefulWidget {
  const Officialsdashboard({super.key});

  @override
  _Officialsdashboard createState() => _Officialsdashboard();
}

class _Officialsdashboard extends State<Officialsdashboard> {
  List<Map<String, dynamic>> _chatGroups = [];
String? _currentUserID;
SharedPreferences? prefs;
bool _isLoading = true;
bool _isUniversityAdmin = false;
bool _isStudent = false; // Add this
int _selectedIndex = 0;


  List<dynamic> posts = [];
  //bool _isDarkMode = false;

 @override
void initState() {
  super.initState();
  //loadTheme();
  fetchPosts();
  _initializePreferences().then((_) {
    _fetchChatGroups();
    _loadUserRole();
  });
}
Future<void> _loadUserRole() async {
  SharedPreferences prefs = await SharedPreferences.getInstance();
  final defaultRole = prefs.getString("defaultRole");
  setState(() {
    _isUniversityAdmin = defaultRole == "universityAdministrator";
    _isStudent = defaultRole == "student"; // Add this
  });
}
List<String> _roleList = [];
Future<void> _initializePreferences() async {
  prefs = await SharedPreferences.getInstance();
  _currentUserID = prefs?.getString("universityID");

  final rolesJson = prefs?.getString("role");
  if (rolesJson != null) {
    List<dynamic> decodedRoles = [];
    try {
      decodedRoles = rolesJson.contains('[')
          ? List<String>.from(jsonDecode(rolesJson))
          : [rolesJson];
    } catch (_) {
      decodedRoles = [rolesJson];
    }

    setState(() {
      _roleList = decodedRoles.cast<String>();
    });
  }

  await _fetchChatGroups();
}



 Future<void> _fetchChatGroups() async {
  if (_currentUserID == null) return;

  try {
  final groups = await GroupService.getChatGroups(_currentUserID!);
  setState(() {
    _chatGroups = groups;
    _isLoading = false;
  });
} catch (e) {
  print("Error loading groups: $e");
  setState(() => _isLoading = false);
}

}


  /*void loadTheme() async {
    final prefs = await SharedPreferences.getInstance();
    setState(() {
      _isDarkMode = prefs.getBool('darkMode') ?? false;
    });
  }*/

  Future<void> fetchPosts() async {
  try {
    final data = await PostService.fetchPublicPosts();
    setState(() {
      posts = data;
    });
  } catch (e) {
    print("Error fetching posts: $e");
  }
}
Future<void> _onSideNavSelected(int index) async {
  setState(() => _selectedIndex = index);
  if (index < _navigationActions.length) {
    await _navigationActions[index]();
  }
}

/*void _onBottomNavSelected(int index) {
  final isWideScreen = MediaQuery.of(context).size.width >= 900;

  switch (index) {
    case 0:
      setState(() => _selectedIndex = 0);
      break;
    case 1:
      if (isWideScreen) {
        setState(() => _selectedIndex = 1);
      } else {
        Navigator.push(context, MaterialPageRoute(builder: (_) => const ChattingGroupPage()));
      }
      break;
    case 2:
      Navigator.push(context, MaterialPageRoute(builder: (_) => settingsPage()));
      break;
  }
}
*/
  Widget _buildShimmerCard() {
    return Card(
      margin: const EdgeInsets.symmetric(vertical: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
      child: Shimmer.fromColors(
        baseColor: Colors.grey[300]!,
        highlightColor: Colors.grey[100]!,
        child: Container(
          height: 250,
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(15),
            color: Colors.white,
          ),
        ),
      ),
    );
  }
/*Future<Size> _getImageSize(String imageUrl) async {
  final Completer<Size> completer = Completer();
  final Image image = Image.network(imageUrl);

  image.image.resolve(const ImageConfiguration()).addListener(
    ImageStreamListener((ImageInfo info, bool _) {
      final myImage = info.image;
      final size = Size(myImage.width.toDouble(), myImage.height.toDouble());
      completer.complete(size);
    }, onError: (error, stackTrace) {
      completer.complete(const Size(16, 9));
    }),
  );

  return completer.future;
}*/

  Widget _buildPostCard(dynamic post) {
    final List<dynamic> mediaList = post['media'] is List ? post['media'] : [];
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
    // Helper for file download/open
    Future<void> _downloadFile(String url, String fileName) async {
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

    return AnimatedContainer(
      duration: const Duration(milliseconds: 300),
      curve: Curves.easeInOut,
      margin: const EdgeInsets.symmetric(vertical: 12, horizontal: 0),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.grey.withOpacity(0.15),
            spreadRadius: 2,
            blurRadius: 8,
            offset: const Offset(0, 4),
          )
        ],
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (context) => Postsdetails(postID: int.parse(post['postID'])),
            ),
          );
        },
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header Row with Avatar and user info
             Row(
              children: [
                const CircleAvatar(
                  radius: 24,
                  backgroundColor: Colors.green,
                  child: Icon(Icons.person, color: Colors.white),
                ),
              //  const SizedBox(width: 10),
                 const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          post['username'],
                          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 2),
                        Text(
                          "(${post['universityID']})",
                          style: const TextStyle(fontSize: 14, color: Colors.grey),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 10),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        post['DATECREATED'],
                        style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                      ),
                      if (_currentUserID != null &&
                          post['universityID']?.toString() == _currentUserID)
                        IconButton(
                          icon: const Icon(Icons.edit, color: Colors.green),
                          tooltip: "Edit Post",
                          onPressed: () => showEditPostDialog(
                          context: context,
                          post: post,
                          currentUserID: _currentUserID,
                          prefs: prefs,
                          reloadPosts: fetchPosts,
),
                        ),
                    ],
                  ),
                ],
              ),
            const SizedBox(height: 10),
              // Divider to separate header and content
              Container(
                height: 1,
                color: Colors.grey[300],
              ),
              const SizedBox(height: 12),
              // Post title and content
              Text(
                post['posttitle'],
                style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              Text(
                post['CONTENT'],
                style: const TextStyle(fontSize: 15, height: 1.4),
                maxLines: 4,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 12),
              // --- Media section for images and files ---
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
                  padding: const EdgeInsets.only(top: 12.0),
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
                            await _downloadFile(fileUrl, fileName);
                          },
                          trailing: Icon(Icons.download, color: Colors.green),
                        );
                      }).toList(),
                    ],
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
  Widget _buildMediaCarousel(List<dynamic> mediaList) {
    // Show all media as images in a sweep (PageView) as before
    int currentIndex = 0;
    return StatefulBuilder(
      builder: (context, setState) {
        return Column(
          children: [
            SizedBox(
              height: 250,
              child: PageView.builder(
                itemCount: mediaList.length,
                onPageChanged: (index) {
                  setState(() {
                    currentIndex = index;
                  });
                },
                itemBuilder: (context, index) {
                  final imgUrl = "${ApiConfig.baseUrl}/${mediaList[index]}";
                  return ClipRRect(
                    borderRadius: BorderRadius.circular(16),
                    child: Image.network(
                      imgUrl,
                      fit: BoxFit.contain,
                      height: 250,
                      errorBuilder: (context, error, stackTrace) => Container(
                        color: Colors.grey[300],
                        height: 250,
                        child: Icon(Icons.broken_image, size: 100, color: Colors.grey[600]),
                      ),
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

List<Future<void> Function()> get _navigationActions {
  return [
    () async {
      // Dashboard (no navigation needed as it's inline)
    },
    () async {
      Navigator.push(context, MaterialPageRoute(builder: (_) => const ProfilePage()));
    },
    () async {
      Navigator.push(context, MaterialPageRoute(builder: (_) => const PrivatePosts()));
    },
    if (!_isUniversityAdmin) // Show only for officials
      () async {
        Navigator.push(context, MaterialPageRoute(builder: (_) => OfficialNotification()));
      },
    () async {
      Navigator.push(context, MaterialPageRoute(builder: (_) => PostFormScreen()));
    },
    () async {
      Navigator.push(context, MaterialPageRoute(builder: (_) => EventFormScreen()));
    },
    () async {
      Navigator.push(context, MaterialPageRoute(builder: (_) => Editactivity(userID: _currentUserID!)));
    },
    () async {
      Navigator.push(context, MaterialPageRoute(builder: (_) => PrivateChats()));
    },
    if (!_isUniversityAdmin)
    () async {
      Navigator.push(context, MaterialPageRoute(builder: (_) => Participators(userID: _currentUserID!)));
    },
    if (_isUniversityAdmin) // Show only for university administrators
      () async {
        Navigator.push(context, MaterialPageRoute(builder: (_) => StatisticsPage()));
      },
    if (_isUniversityAdmin) // Show only for university administrators
      () async {
        Navigator.push(context, MaterialPageRoute(builder: (_) => RegistrationPage()));
      },
      if (_isUniversityAdmin) // Show only for university administrators
      () async {
        Navigator.push(context, MaterialPageRoute(builder: (_) => SuspendStudentPage()));
      },
    () async {
      SharedPreferences prefs = await SharedPreferences.getInstance();
      final universityID = prefs.getString("universityID");
      final defaultRole = prefs.getString("defaultRole");

      final Map<String, int> roleMap = {
        "official": 1,
        "academic": 2,
        "universityAdministrator": 3,
      };

      final roleID = roleMap[defaultRole];
      final userID = int.tryParse(universityID ?? '');

      if (roleID != null && userID != null) {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => CreateCustomGroupScreen(
              createdByUserID: userID,
              roleID: roleID
              //userFaculty: prefs.getString("facultyName") ?? "",
            ),
          ),
        );
      }
    },
    
      
   /* () async {
      Navigator.push(context, MaterialPageRoute(builder: (_) => Calender()));
    },*/
    () async {
      Navigator.push(context, MaterialPageRoute(builder: (_) => settingsPage()));
    },
    
  ];
}


  Widget _buildSideNav() {
  return SingleChildScrollView(
    child: ConstrainedBox(
      constraints: BoxConstraints(
        minHeight: 0,
        maxHeight: MediaQuery.of(context).size.height,
      ),
      child: IntrinsicHeight(
        child: NavigationRail(
          selectedIndex: _selectedIndex,
          onDestinationSelected: _onSideNavSelected,
          labelType: NavigationRailLabelType.all,
          backgroundColor: Colors.white,
          destinations: [
            const NavigationRailDestination(
              icon: Icon(Icons.dashboard_outlined),
              label: Text("Dashboard"),
            ),
            const NavigationRailDestination(
              icon: Icon(Icons.person_2_outlined),
              label: Text("Profile"),
            ),
            const NavigationRailDestination(
              icon: Icon(Icons.privacy_tip_outlined),
              label: Text("Private Posts"),
            ),
            if (!_isUniversityAdmin) // Show only for officials
              const NavigationRailDestination(
                icon: Icon(Icons.chat_outlined),
                label: Text("Notifications"),
              ),
            const NavigationRailDestination(
              icon: Icon(Icons.post_add),
              label: Text("Create new Posts"),
            ),
            const NavigationRailDestination(
              icon: Icon(Icons.create_outlined),
              label: Text("Create new Events"),
            ),
            const NavigationRailDestination(
              icon: Icon(Icons.edit_note),
              label: Text("Edit Events"),
            ),
           const  NavigationRailDestination(
             icon: Icon(Icons.personal_injury),
             label: Text("Private Chats"),
           ),
           if (!_isUniversityAdmin)
            const NavigationRailDestination(
              icon: Icon(Icons.list),
              label: Text("View participators"),
            ),
            if (_isUniversityAdmin) // Show only for university administrators
              const NavigationRailDestination(
                icon: Icon(Icons.rate_review),
                label: Text("Statistics"),
              ),
            if (_isUniversityAdmin) // Show only for university administrators
              const NavigationRailDestination(
                icon: Icon(Icons.person_add),
                label: Text("Registration"),
              ),
              if (_isUniversityAdmin) // Show only for university administrators
              const NavigationRailDestination(
                icon: Icon(Icons.person_off),
                label: Text("Suspend Student"),
              ),
            const NavigationRailDestination(
              icon: Icon(Icons.group_add),
              label: Text("Costume Groups"),
            ),
           /* const NavigationRailDestination(
              icon: Icon(Icons.calendar_month_rounded),
              label: Text("Calender"),
            ),*/
            const NavigationRailDestination(
              icon: Icon(Icons.settings),
              label: Text("Settings"),
            ),
          ],
        ),
      ),
    ),
  );
}

  // Add this method for Drawer navigation
  Widget _buildDrawerNavigation() {
    return ListView(
      padding: EdgeInsets.zero,
      children: [
        DrawerHeader(
          decoration: const BoxDecoration(
            color: Colors.green,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const CircleAvatar(
                radius: 28,
                backgroundColor: Colors.white,
                child: Icon(Icons.person, size: 36, color: Colors.green),
              ),
              const SizedBox(height: 12),
              Text(
                prefs?.getString("username") ?? "Official",
                style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
              ),
              Text(
                _currentUserID ?? "",
                style: const TextStyle(color: Colors.white70, fontSize: 14),
              ),
            ],
          ),
        ),
        ListTile(
          leading: const Icon(Icons.dashboard_outlined),
          title: const Text('Dashboard'),
          onTap: () {
            Navigator.pop(context);
            setState(() => _selectedIndex = 0);
          },
        ),
        ListTile(
          leading: const Icon(Icons.person_2_outlined),
          title: const Text('Profile'),
          onTap: () {
            Navigator.pop(context);
            Navigator.push(context, MaterialPageRoute(builder: (context) => const ProfilePage()));
          },
        ),
        ListTile(
          leading: const Icon(Icons.privacy_tip_outlined),
          title: const Text('Private Posts'),
          onTap: () {
            Navigator.pop(context);
            Navigator.push(context, MaterialPageRoute(builder: (context) => const PrivatePosts()));
          },
        ),
        if (!_isUniversityAdmin)
          ListTile(
            leading: const Icon(Icons.chat_outlined),
            title: const Text('Notifications'),
            onTap: () {
              Navigator.pop(context);
              Navigator.push(context, MaterialPageRoute(builder: (context) => OfficialNotification()));
            },
          ),
          ListTile(
          leading: const Icon(Icons.private_connectivity),
          title: const Text('Private Chats'),
          onTap: () {
            Navigator.pop(context);
            Navigator.push(context, MaterialPageRoute(builder: (context) => PrivateChats()));
          },
        ),
          ListTile(
            leading: const Icon(Icons.group),
            
            title: const Text('Chatting Groups'),
            onTap: () {
              Navigator.pop(context);
              Navigator.push(context, MaterialPageRoute(builder: (context) => ChattingGroupPage()));
            },
          ),
          /* ListTile(
            leading: const Icon(Icons.private_connectivity),
            
            title: const Text('Private Chatting'),
            onTap: () {
              Navigator.pop(context);
              Navigator.push(context, MaterialPageRoute(builder: (context) => ChattingGroupPage()));
            },
          ),*/
        ListTile(
          leading: const Icon(Icons.post_add),
          title: const Text('Create new Posts'),
          onTap: () {
            Navigator.pop(context);
            Navigator.push(context, MaterialPageRoute(builder: (context) => PostFormScreen()));
          },
        ),
        ListTile(
          leading: const Icon(Icons.create_outlined),
          title: const Text('Create new Events'),
          onTap: () {
            Navigator.pop(context);
            Navigator.push(context, MaterialPageRoute(builder: (context) => EventFormScreen()));
          },
        ),
        ListTile(
          leading: const Icon(Icons.edit_note),
          title: const Text('Edit Events'),
          onTap: () {
            Navigator.pop(context);
            Navigator.push(context, MaterialPageRoute(builder: (context) => Editactivity(userID: _currentUserID!)));
          },
        ),
        if (!_isUniversityAdmin)
        ListTile(
          leading: const Icon(Icons.list),
          title: const Text('View participators'),
          onTap: () {
            Navigator.pop(context);
            Navigator.push(context, MaterialPageRoute(builder: (context) => Participators(userID: _currentUserID!)));
          },
        ),
        if (_isUniversityAdmin)
          ListTile(
            leading: const Icon(Icons.rate_review),
            title: const Text('Statistics'),
            onTap: () {
              Navigator.pop(context);
              Navigator.push(context, MaterialPageRoute(builder: (context) => StatisticsPage()));
            },
          ),
        if (_isUniversityAdmin)
          ListTile(
            leading: const Icon(Icons.person_add),
            title: const Text('Registration'),
            onTap: () {
              Navigator.pop(context);
              Navigator.push(context, MaterialPageRoute(builder: (context) => RegistrationPage()));
            },
          ),
          if (_isUniversityAdmin)
          ListTile(
            leading: const Icon(Icons.person_off),
            title: const Text('Suspend Student'),
            onTap: () {
              Navigator.pop(context);
              Navigator.push(context, MaterialPageRoute(builder: (context) => SuspendStudentPage()));
            },
          ),
        ListTile(
          leading: const Icon(Icons.group_add),
          title: const Text('Costume Groups'),
          onTap: () async {
            Navigator.pop(context);
            SharedPreferences prefs = await SharedPreferences.getInstance();
            final universityID = prefs.getString("universityID");
            final defaultRole = prefs.getString("defaultRole");
            final Map<String, int> roleMap = {
              "official": 1,
              "academic": 2,
              "universityAdministrator": 3,
            };
            final roleID = roleMap[defaultRole];
            final userID = int.tryParse(universityID ?? '');
            if (roleID != null && userID != null) {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => CreateCustomGroupScreen(
                    createdByUserID: userID,
                    roleID: roleID,
                  ),
                ),
              );
            }
          },
        ),
        /*ListTile(
          leading: const Icon(Icons.calendar_month_rounded),
          title: const Text('Calender'),
          onTap: () {
            Navigator.pop(context);
            Navigator.push(context, MaterialPageRoute(builder: (context) => Calender()));
          },
        ),*/
        ListTile(
          leading: const Icon(Icons.settings),
          title: const Text('Settings'),
          onTap: () {
            Navigator.pop(context);
            Navigator.push(context, MaterialPageRoute(builder: (context) => settingsPage()));
          },
        ),
      ],
    );
  }

  // --- Student-specific helpers ---
  Future<List<Map<String, dynamic>>> fetchStudentActivities(String userID) async {
    final url = Uri.parse('${ApiConfig.baseUrl}/get_user_activities.php?userID=$userID');
    try {
      final response = await http.get(url);
      if (response.statusCode == 200) {
        final decoded = jsonDecode(response.body);
        if (decoded['success'] == true && decoded['activities'] is List) {
          return List<Map<String, dynamic>>.from(decoded['activities']);
        }
      }
    } catch (e) {
      print("Error fetching activities: $e");
    }
    return [];
  }

  void _showStudentUpdatesDialog() async {
    final FirebaseFirestore firestore = FirebaseFirestore.instance;
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    final String? currentUserId = prefs.getString("universityID");

    if (currentUserId == null) return;

    showDialog(
      context: context,
      builder: (context) {
        int newPrivateMessages = 0;
        int newGroupMessages = 0;
        List<Map<String, dynamic>> studentActivities = [];
        bool isLoading = true;

        return StatefulBuilder(
          builder: (context, setState) {
            if (isLoading) {
              Future(() async {
                int privateCount = 0;
                int groupCount = 0;

                final privateChatsSnapshot = await firestore.collection('PrivateChats').get();
                for (var chatDoc in privateChatsSnapshot.docs) {
                  final messagesSnapshot = await firestore
                      .collection('PrivateChats')
                      .doc(chatDoc.id)
                      .collection('Messages')
                      .where('receiverID', isEqualTo: currentUserId)
                      .get();
                  privateCount += messagesSnapshot.docs.length;
                }

                final groupChatsSnapshot = await firestore.collection('Groups').get();
                for (var groupDoc in groupChatsSnapshot.docs) {
                  final messagesSnapshot = await firestore
                      .collection('Groups')
                      .doc(groupDoc.id)
                      .collection('Messages')
                      .where('senderID', isNotEqualTo: currentUserId)
                      .get();
                  groupCount += messagesSnapshot.docs.length;
                }

                final activities = await fetchStudentActivities(currentUserId);

                setState(() {
                  newPrivateMessages = privateCount;
                  newGroupMessages = groupCount;
                  studentActivities = activities;
                  isLoading = false;
                });
              });
            }

            return AlertDialog(
              title: const Text("New Updates"),
              content: isLoading
                  ? const SizedBox(height: 60, child: Center(child: CircularProgressIndicator()))
                  : SingleChildScrollView(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text("New private messages: $newPrivateMessages"),
                          const SizedBox(height: 8),
                          Text("New group messages: $newGroupMessages"),
                          const SizedBox(height: 8),
                          Text("Your Activities:"),
                          ...studentActivities.map((activity) => Padding(
                                padding: const EdgeInsets.only(top: 4.0),
                                child: Text(
                                    "- ${activity['activityName']} on ${activity['activityDate']}"),
                              )),
                        ],
                      ),
                    ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text("Close"),
                ),
              ],
            );
          },
        );
      },
    );
  }
  // --- End student helpers ---

  // --- Student navigation actions ---
  List<Future<void> Function()> get _studentNavigationActions {
    return [
      () async {}, // Dashboard
      () async {
        Navigator.push(context, MaterialPageRoute(builder: (_) => const ProfilePage()));
      },
      () async {
        Navigator.push(context, MaterialPageRoute(builder: (_) => const PrivatePosts()));
      },
      () async {
        Navigator.push(context, MaterialPageRoute(builder: (_) => PostFormScreen()));
      },
      () async {
        if (_currentUserID != null) {
          Navigator.push(context, MaterialPageRoute(builder: (_) => Participate(userID: _currentUserID!)));
        }
      },
      () async {
        Navigator.push(context, MaterialPageRoute(builder: (_) => AcademicRoomPage()));
      },
      () async {
        Navigator.push(context, MaterialPageRoute(builder: (_) => RejectedPostsPage()));
      },
      () async {
        Navigator.push(context, MaterialPageRoute(builder: (_) => settingsPage()));
      },
    ];
  }
  // --- End student navigation actions ---

  // --- Merge navigation actions ---
  /*List<Future<void> Function()> get _mergedNavigationActions {
    if (_isStudent) return _studentNavigationActions;
    return _navigationActions;
  }*/
  // --- End merge navigation actions ---

  // --- Merge side nav ---
  Widget _buildMergedSideNav() {
    if (_isStudent) {
      return SingleChildScrollView(
        child: ConstrainedBox(
          constraints: BoxConstraints(
            minHeight: 0,
            maxHeight: MediaQuery.of(context).size.height,
          ),
          child: IntrinsicHeight(
            child: NavigationRail(
              selectedIndex: _selectedIndex,
              onDestinationSelected: (idx) async {
                setState(() => _selectedIndex = idx);
                if (idx < _studentNavigationActions.length) {
                  await _studentNavigationActions[idx]();
                }
              },
              labelType: NavigationRailLabelType.all,
              backgroundColor: Colors.white,
              destinations: const [
                NavigationRailDestination(
                  icon: Icon(Icons.dashboard_outlined),
                  label: Text("Dashboard"),
                ),
                NavigationRailDestination(
                  icon: Icon(Icons.person_2_outlined),
                  label: Text("Profile"),
                ),
                NavigationRailDestination(
                  icon: Icon(Icons.privacy_tip_outlined),
                  label: Text("Private Posts"),
                ),
                NavigationRailDestination(
                  icon: Icon(Icons.post_add),
                  label: Text("Create new Posts"),
                ),
                NavigationRailDestination(
                  icon: Icon(Icons.create_outlined),
                  label: Text("Participate in Events"),
                ),
                NavigationRailDestination(
                  icon: Icon(Icons.info_sharp),
                  label: Text("Academics' Rooms"),
                ),
                NavigationRailDestination(
                  icon: Icon(Icons.recycling),
                  label: Text("Rejected Posts"),
                ),
                NavigationRailDestination(
                  icon: Icon(Icons.settings),
                  label: Text("Settings"),
                ),
              ],
            ),
          ),
        ),
      );
    } else {
      return _buildSideNav();
    }
  }
  // --- End merge side nav ---

  // --- Merge drawer navigation ---
  Widget _buildMergedDrawerNavigation() {
    if (_isStudent) {
      return ListView(
        padding: EdgeInsets.zero,
        children: [
          DrawerHeader(
            decoration: const BoxDecoration(
              color: Colors.green,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const CircleAvatar(
                  radius: 28,
                  backgroundColor: Colors.white,
                  child: Icon(Icons.person, size: 36, color: Colors.green),
                ),
                const SizedBox(height: 12),
                Text(
                  prefs?.getString("username") ?? "Student",
                  style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                ),
                Text(
                  _currentUserID ?? "",
                  style: const TextStyle(color: Colors.white70, fontSize: 14),
                ),
              ],
            ),
          ),
          ListTile(
            leading: const Icon(Icons.dashboard_outlined),
            title: const Text('Dashboard'),
            onTap: () {
              Navigator.pop(context);
              setState(() => _selectedIndex = 0);
            },
          ),
          ListTile(
            leading: const Icon(Icons.person_2_outlined),
            title: const Text('Profile'),
            onTap: () {
              Navigator.pop(context);
              Navigator.push(context, MaterialPageRoute(builder: (context) => const ProfilePage()));
            },
          ),
          ListTile(
            leading: const Icon(Icons.privacy_tip_outlined),
            title: const Text('Private Posts'),
            onTap: () {
              Navigator.pop(context);
              Navigator.push(context, MaterialPageRoute(builder: (context) => const PrivatePosts()));
            },
          ),
          ListTile(
            leading: const Icon(Icons.post_add),
            title: const Text('Create new Posts'),
            onTap: () {
              Navigator.pop(context);
              Navigator.push(context, MaterialPageRoute(builder: (context) => PostFormScreen()));
            },
          ),
          ListTile(
            leading: const Icon(Icons.create_outlined),
            title: const Text('Participate in Events'),
            onTap: () {
              Navigator.pop(context);
              if (_currentUserID != null) {
                Navigator.push(context, MaterialPageRoute(builder: (context) => Participate(userID: _currentUserID!)));
              }
            },
          ),
          ListTile(
            leading: const Icon(Icons.info_sharp),
            title: const Text("Academics' Rooms"),
            onTap: () {
              Navigator.pop(context);
              Navigator.push(context, MaterialPageRoute(builder: (context) => AcademicRoomPage()));
            },
          ),
          ListTile(
            leading: const Icon(Icons.recycling),
            title: const Text('Rejected Posts'),
            onTap: () {
              Navigator.pop(context);
              Navigator.push(context, MaterialPageRoute(builder: (context) => RejectedPostsPage()));
            },
          ),
          ListTile(
            leading: const Icon(Icons.group),
            title: const Text('Group Chats'),
            onTap: () {
              Navigator.pop(context);
              Navigator.push(context, MaterialPageRoute(builder: (context) => ChattingGroupPage()));
            },
          ),
          ListTile(
            leading: const Icon(Icons.settings),
            title: const Text('Settings'),
            onTap: () {
              Navigator.pop(context);
              Navigator.push(context, MaterialPageRoute(builder: (context) => settingsPage()));
            },
          ),
        ],
      );
    } else {
      return _buildDrawerNavigation();
    }
  }
  // --- End merge drawer navigation ---

  // --- Student post card (optional: use if you want file/image separation for students) ---
  Widget _buildStudentPostCard(dynamic post) {
    final List<dynamic> mediaList = post['media'] is List ? post['media'] : [];
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
    final String? currentUserId = _currentUserID;
    Future<void> _downloadFile(String url, String fileName) async {
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
    return AnimatedContainer(
      duration: const Duration(milliseconds: 300),
      curve: Curves.easeInOut,
      margin: const EdgeInsets.symmetric(vertical: 12, horizontal: 0),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.grey.withOpacity(0.15),
            spreadRadius: 2,
            blurRadius: 8,
            offset: const Offset(0, 4),
          )
        ],
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (context) => Postsdetails(postID: int.parse(post['postID'])),
            ),
          );
        },
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const CircleAvatar(
                    radius: 24,
                    backgroundColor: Colors.green,
                    child: Icon(Icons.person, color: Colors.white),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          post['username'],
                          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 2),
                        Text(
                          "(${post['universityID']})",
                          style: const TextStyle(fontSize: 14, color: Colors.grey),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 10),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        post['DATECREATED'],
                        style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                      ),
                      if (currentUserId != null &&
                          post['universityID']?.toString() == currentUserId)
                        IconButton(
                          icon: const Icon(Icons.edit, color: Colors.green),
                          tooltip: "Edit Post",
                          onPressed: () => showEditPostDialog(
                            context: context,
                            post: post,
                            currentUserID: _currentUserID,
                            prefs: prefs,
                            reloadPosts: fetchPosts,
                          ),
                        ),
                    ],
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Container(
                height: 1,
                color: Colors.grey[300],
              ),
              const SizedBox(height: 12),
              Text(
                post['posttitle'],
                style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              Text(
                post['CONTENT'],
                style: const TextStyle(fontSize: 15, height: 1.4),
                maxLines: 4,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 12),
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
                  padding: const EdgeInsets.only(top: 12.0),
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
                            await _downloadFile(fileUrl, fileName);
                          },
                          trailing: Icon(Icons.download, color: Colors.green),
                        );
                      }).toList(),
                    ],
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
  // --- End student post card ---

  @override
  Widget build(BuildContext context) {
    final bool isWideScreen = MediaQuery.of(context).size.width > 900;

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
            const SizedBox(width: 8),
            Text(
              _isStudent ? "Student Dashboard" : "Officials' Dashboard",
              style: const TextStyle(
                color: Colors.green,
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.notifications, color: Colors.green),
            tooltip: "New Updates",
            onPressed: () {
              if (_isStudent) {
                _showStudentUpdatesDialog();
              } else {
                _showUpdatesDialog();
              }
            },
          ),
        ],
      ),
      // Drawer for small screens, as in academic/student dashboards
      drawer: !isWideScreen ? Drawer(child: _buildMergedDrawerNavigation()) : null,
      body: Row(
        children: [
          if (isWideScreen) _buildMergedSideNav(),

          Expanded(
            flex: 3,
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 700),
                child: posts.isEmpty
                    ? ListView.builder(
                        padding: const EdgeInsets.all(16),
                        itemCount: 6,
                        itemBuilder: (context, index) => _buildShimmerCard(),
                      )
                    : RefreshIndicator(
                        onRefresh: fetchPosts,
                        child: ListView.builder(
                          padding: const EdgeInsets.all(16),
                          itemCount: posts.length,
                          itemBuilder: (context, index) => _isStudent
                              ? _buildStudentPostCard(posts[index])
                              : _buildPostCard(posts[index]),
                        ),
                      ),
              ),
            ),
          ),

          if (isWideScreen)
            Expanded(
              flex: 1,
              child: Container(
                padding: const EdgeInsets.all(12),
                color: Colors.white,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const ListTile(
                      leading: Icon(Icons.group, color: Colors.green),
                      title: Text("My Communities"),
                    ),
                    Expanded(
                      child: _isLoading
                          ? const Center(child: CircularProgressIndicator())
                          : _chatGroups.isEmpty
                              ? const Center(child: Text("No groups found"))
                              : ListView.builder(
                                  itemCount: _chatGroups.length,
                                  itemBuilder: (context, index) {
                                    var group = _chatGroups[index];
                                    return Card(
                                      margin: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
                                      shape: RoundedRectangleBorder(
                                        borderRadius: BorderRadius.circular(12),
                                      ),
                                      elevation: 2,
                                      child: ListTile(
                                        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                                        leading: CircleAvatar(
                                          backgroundColor: Colors.green.shade100,
                                          child: Icon(Icons.group, color: Colors.green),
                                        ),
                                        title: Text(
                                          group['groupName'],
                                          style: const TextStyle(
                                            fontWeight: FontWeight.w600,
                                            fontSize: 16,
                                          ),
                                        ),
                                        trailing: const Icon(Icons.arrow_forward_ios, size: 16),
                                        onTap: () {
                                          String? senderUsername = prefs?.getString("username");
                                          if (senderUsername != null) {
                                            Navigator.push(
                                              context,
                                              MaterialPageRoute(
                                                builder: (context) => ChattingPage(
                                                  groupId: group['groupID'],
                                                  groupName: group['groupName'],
                                                  senderUsername: senderUsername,
                                                ),
                                              ),
                                            );
                                          }
                                        },
                                      ),
                                    );
                                  },
                                ),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
      // Remove bottomNavigationBar for small screens
      // bottomNavigationBar: isWideScreen
      //   ? null
      //   : BottomNavigationBar(
      //       onTap: _onBottomNavSelected,
      //       currentIndex: 0,
      //       items: const [
      //         BottomNavigationBarItem(icon: Icon(Icons.dashboard), label: 'Dashboard'),
      //         BottomNavigationBarItem(icon: Icon(Icons.chat), label: 'Chat'),
      //         BottomNavigationBarItem(icon: Icon(Icons.settings), label: 'Settings'),
      //       ],
      //     ),
      floatingActionButton: (!_isStudent && (_roleList.contains("academic") || _roleList.contains("official")) && MediaQuery.of(context).size.width > 900)
          ? FloatingActionButton(
              onPressed: _createGroups,
              backgroundColor: Colors.green,
              child: const Icon(Icons.group_add),
            )
          : null,
    );
  }
   
bool _isCreating = false;
  Future<void> _createGroups() async {
  if (_isCreating) return;

  setState(() {
    _isCreating = true;
  });

if (_currentUserID != null && (_roleList.contains("academic") || _roleList.contains("official"))) {
    final response = await GroupService.createGroups(_currentUserID!, _roleList.last);

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(response['message'] ?? "Something happened")),
    );

    if (response['success']) {
      await _fetchChatGroups();
    }
  } else {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text("Only academics and officials can create groups")),
    );
  }

  setState(() {
    _isCreating = false;
  });
}
Future<int> fetchPendingPostCount(String reviewerId) async {
  try {
    final url = Uri.parse('${ApiConfig.baseUrl}/get_pending_posts_count.php?reviewerId=$reviewerId');
    final response = await http.get(url);

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      return data['count'] ?? 0;
    }
  } catch (e) {
    print("Error fetching count: $e");
  }
  return 0;
}

void _showUpdatesDialog() async {
  final FirebaseFirestore firestore = FirebaseFirestore.instance;
  final SharedPreferences prefs = await SharedPreferences.getInstance();
  final String? currentUserId = prefs.getString("universityID");

  if (currentUserId == null) return;

  showDialog(
    context: context,
    builder: (context) {
      // State inside the dialog
      int newPrivateMessages = 0;
      int newGroupMessages = 0;
      int pendingNotifications = 0;
      bool isLoading = true;

      return StatefulBuilder(
        builder: (context, setState) {
     if (isLoading) {
  Future(() async {
    int privateCount = 0;
    int groupCount = 0;

    final privateChatsSnapshot = await firestore.collection('PrivateChats').get();
    for (var chatDoc in privateChatsSnapshot.docs) {
      final messagesSnapshot = await firestore
          .collection('PrivateChats')
          .doc(chatDoc.id)
          .collection('Messages')
          .where('receiverID', isEqualTo: currentUserId)
          .get();
      privateCount += messagesSnapshot.docs.length;
    }

    final groupChatsSnapshot = await firestore.collection('Groups').get();
    for (var groupDoc in groupChatsSnapshot.docs) {
      final messagesSnapshot = await firestore
          .collection('Groups')
          .doc(groupDoc.id)
          .collection('Messages')
          .where('senderID', isNotEqualTo: currentUserId)
          .get();
      groupCount += messagesSnapshot.docs.length;
    }

    final pendingCount = await fetchPendingPostCount(currentUserId);

    setState(() {
      newPrivateMessages = privateCount;
      newGroupMessages = groupCount;
      pendingNotifications = pendingCount;
      isLoading = false; // trigger UI update
    });
  });
}


          return AlertDialog(
            title: const Text("New Updates"),
            content: 
              isLoading ? const SizedBox(height:60, child: Center(child: CircularProgressIndicator()),)
             : Column(
               mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
               children: [
               Text("New private messages: $newPrivateMessages"),
               const SizedBox(height: 8),
               Text("New group messages: $newGroupMessages"),
               const SizedBox(height: 8),
               Text("New pending approval Posts: $pendingNotifications"),
              ],
    

            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: const Text("Close"),
              ),
            ],
          );
        },
      );
    },
  );
}




}
