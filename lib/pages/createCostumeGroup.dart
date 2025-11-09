import 'package:bzu_leads/services/ApiConfig.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';

class CreateCustomGroupScreen extends StatefulWidget {
  final int createdByUserID;
  final int roleID;

  const CreateCustomGroupScreen({required this.createdByUserID, required this.roleID});

  @override
  _CreateCustomGroupScreenState createState() => _CreateCustomGroupScreenState();
}

class _CreateCustomGroupScreenState extends State<CreateCustomGroupScreen> {
  final TextEditingController _groupNameController = TextEditingController();
  final TextEditingController _searchController = TextEditingController();
  final List<Map<String, dynamic>> _selectedUsers = [];
  List<Map<String, dynamic>> _searchResults = [];
  List<Map<String, dynamic>> _users = [];
  List<Map<String, dynamic>> _faculties = [];
  List<Map<String, dynamic>> _departments = [];
  String? _selectedFacultyID;
  String? _selectedDepartmentID;

  @override
  void initState() {
    super.initState();
    _fetchFaculties();
    // Add the creator to the selected users by default
    _selectedUsers.add({
      'username': 'You',
      'universityID': widget.createdByUserID,
    });
  }

  Future<void> _fetchFaculties() async {
    final response = await http.get(Uri.parse('${ApiConfig.baseUrl}/get_faculties.php'));
    if (response.statusCode == 200) {
      final List<dynamic> faculties = json.decode(response.body);
      setState(() {
        // Ensure correct mapping and that the list is not empty
        _faculties = faculties
            .map<Map<String, dynamic>>((f) => {
                  'facultyID': f['facultyID'].toString(),
                  'facultyName': f['facultyName'].toString(),
                })
            .toList();
      });
    } else {
      setState(() {
        _faculties = [];
      });
    }
  }

  Future<void> _fetchDepartments(String facultyID) async {
    final response = await http.get(Uri.parse('${ApiConfig.baseUrl}/get_departments.php?facultyID=$facultyID'));
    if (response.statusCode == 200) {
      final List<dynamic> departments = json.decode(response.body);
      setState(() {
        _departments = departments.map((d) => {
          'departmentID': d['departmentID'].toString(),
          'departmentName': d['departmentName']
        }).toList();
        _selectedDepartmentID = null;
      });
    }
  }

  Future<void> _fetchUsers({String? facultyID, String? departmentID}) async {
    String url;
    if (departmentID != null && departmentID.isNotEmpty) {
      url = '${ApiConfig.baseUrl}/get_users.php?department=$departmentID';
    } else if (facultyID != null && facultyID.isNotEmpty) {
      url = '${ApiConfig.baseUrl}/get_users.php?facultyID=$facultyID';
    } else {
      setState(() {
        _users = [];
      });
      return;
    }
    final response = await http.get(Uri.parse(url));
    if (response.statusCode == 200) {
      final List<dynamic> users = json.decode(response.body);
      setState(() {
        _users = users
            .map((user) => {
              'universityID': user['universityID'],
                  'username': user['username'],
                  
                })
            .where((user) => !_selectedUsers.any((u) => u['universityID'] == user['universityID']))
            .toList();
      });
    }
  }

  Future<void> _searchUsers(String query) async {
    if (query.isEmpty) {
      setState(() {
        _searchResults = [];
      });
      return;
    }
    final response = await http.get(
      Uri.parse('${ApiConfig.baseUrl}/search_users.php?query=$query'),
    );
    if (response.statusCode == 200) {
      final List<dynamic> users = json.decode(response.body);
      setState(() {
        _searchResults = users
            .map((user) => {
                  
                  'universityID': user['universityID'],
                  'username': user['username'],
                })
            .where((user) => !_selectedUsers.any((u) => u['universityID'] == user['universityID']))
            .toList();
      });
    } else {
      setState(() {
        _searchResults = [];
      });
    }
  }

  Future<void> _submitGroup() async {
    if (_groupNameController.text.isEmpty || _selectedUsers.isEmpty) return;

    final response = await http.post(
      Uri.parse('${ApiConfig.baseUrl}/create_custom_group.php'),
      headers: {'Content-Type': 'application/json'},
      body: json.encode({
        'groupName': _groupNameController.text,
        'createdBy': widget.createdByUserID,
        'roleID': widget.roleID,
        'members': _selectedUsers.map((u) => u['universityID']).toList(),
      }),
    );

    if (response.statusCode == 200) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Group Created Successfully!')));
      Navigator.pop(context);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Failed to create group.')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final keyboardVisible = MediaQuery.of(context).viewInsets.bottom != 0;

    // Add controllers for scrollbars
    final ScrollController usersScrollController = ScrollController();
    final ScrollController searchScrollController = ScrollController();

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
            const Text(
              "Create Costume Group",
              style: TextStyle(color: Colors.green),
            ),
          ],
        ),
      ),
      body: GestureDetector(
        onTap: () => FocusScope.of(context).unfocus(),
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              TextField(
                controller: _groupNameController,
                decoration: InputDecoration(labelText: 'Group Name'),
              ),
              SizedBox(height: 20),
              DropdownButtonFormField<String>(
                value: _selectedFacultyID,
                decoration: InputDecoration(labelText: 'Select Faculty (optional)'),
                isExpanded: true,
                items: [
                  const DropdownMenuItem<String>(
                    value: null,
                    child: Text('All Faculties', overflow: TextOverflow.ellipsis),
                  ),
                  ..._faculties.map<DropdownMenuItem<String>>((faculty) {
                    // Defensive: ensure facultyID and facultyName are not null
                    final id = faculty['facultyID']?.toString() ?? '';
                    final name = faculty['facultyName']?.toString() ?? '';
                    return DropdownMenuItem<String>(
                      value: id,
                      child: Text(name, overflow: TextOverflow.ellipsis),
                    );
                  }).toList(),
                ],
                onChanged: (value) {
                  setState(() {
                    _selectedFacultyID = value;
                    _departments = [];
                    _selectedDepartmentID = null;
                    _users = [];
                  });
                  if (value != null && value.isNotEmpty) {
                    _fetchDepartments(value);
                    _fetchUsers(facultyID: value);
                  }
                },
              ),
              SizedBox(height: 20),
              if (_selectedFacultyID != null && _departments.isNotEmpty)
                DropdownButtonFormField<String>(
                  value: _selectedDepartmentID,
                  decoration: InputDecoration(labelText: 'Select Department (optional)'),
                  isExpanded: true, // Fix overflow by expanding dropdown
                  items: [
                    const DropdownMenuItem<String>(
                      value: null,
                      child: Text('All Departments', overflow: TextOverflow.ellipsis),
                    ),
                    ..._departments.map<DropdownMenuItem<String>>((department) {
                      return DropdownMenuItem<String>(
                        value: department['departmentID'],
                        child: Text(
                          department['departmentName'],
                          overflow: TextOverflow.ellipsis,
                        ),
                      );
                    }).toList(),
                  ],
                  onChanged: (value) {
                    setState(() {
                      _selectedDepartmentID = value;
                      _users = [];
                    });
                    if (value != null) {
                      _fetchUsers(departmentID: value);
                    } else if (_selectedFacultyID != null) {
                      _fetchUsers(facultyID: _selectedFacultyID);
                    }
                  },
                ),
              SizedBox(height: 20),
              if (_selectedFacultyID != null && _users.isNotEmpty)
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text("Users in selected faculty${_selectedDepartmentID != null ? ' and department' : ''}:"),
                    SizedBox(height: 10),
                    Container(
                      height: 200,
                      decoration: BoxDecoration(
                        border: Border.all(color: Colors.grey.shade300),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Scrollbar(
                        thumbVisibility: true,
                        controller: usersScrollController,
                        child: ListView.separated(
                          controller: usersScrollController,
                          shrinkWrap: true,
                          physics: AlwaysScrollableScrollPhysics(),
                          padding: EdgeInsets.all(8),
                          itemCount: _users.length,
                          separatorBuilder: (_, __) => Divider(),
                          itemBuilder: (context, index) {
                            final user = _users[index];
                            return ListTile(
                              title: Text(user['username']),
                              leading: CircleAvatar(child: Text(user['username'][0])),
                              trailing: IconButton(
                                icon: Icon(Icons.person_add, color: Colors.green),
                                onPressed: () {
                                  setState(() {
                                    _selectedUsers.add(user);
                                    _users.removeAt(index);
                                  });
                                },
                              ),
                            );
                          },
                        ),
                      ),
                    ),
                  ],
                ),
              if (_selectedFacultyID == null) ...[
                SizedBox(height: 20),
                // The search text field appears here when no faculty is selected.
                // To test: type a query and press the search icon or Enter.
                TextField(
                  controller: _searchController,
                  decoration: InputDecoration(
                    labelText: 'Search users',
                    suffixIcon: IconButton(
                      icon: Icon(Icons.search),
                      onPressed: () => _searchUsers(_searchController.text),
                    ),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(15)),
                  ),
                  onSubmitted: _searchUsers,
                ),
                SizedBox(height: 10),
                if (_searchResults.isNotEmpty)
                  Container(
                    height: 200,
                    decoration: BoxDecoration(
                      border: Border.all(color: Colors.grey.shade300),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Scrollbar(
                      thumbVisibility: true,
                      controller: searchScrollController,
                      child: ListView.separated(
                        controller: searchScrollController,
                        shrinkWrap: true,
                        physics: AlwaysScrollableScrollPhysics(),
                        padding: EdgeInsets.all(8),
                        itemCount: _searchResults.length,
                        separatorBuilder: (_, __) => Divider(),
                        itemBuilder: (context, index) {
                          final user = _searchResults[index];
                          return ListTile(
                            title: Text(user['username']),
                            leading: CircleAvatar(child: Text(user['username'][0])),
                            trailing: IconButton(
                              icon: Icon(Icons.person_add, color: Colors.green),
                              onPressed: () {
                                setState(() {
                                  _selectedUsers.add(user);
                                  _searchResults.removeAt(index);
                                });
                              },
                            ),
                          );
                        },
                      ),
                    ),
                  ),
              ],
              SizedBox(height: 10),
              Wrap(
                spacing: 8,
                children: _selectedUsers.map((user) {
                  return Chip(
                    label: Text(user['username']),
                    onDeleted: () {
                      setState(() => _selectedUsers.remove(user));
                    },
                  );
                }).toList(),
              ),
              SizedBox(height: 20),
              if (!keyboardVisible)
                Center(
                  child: ElevatedButton.icon(
                    onPressed: _submitGroup,
                    icon: Icon(Icons.group_add, color: Colors.white),
                    label: Text('Create Group', style: TextStyle(color: Colors.white)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.green,
                      padding: EdgeInsets.symmetric(horizontal: 24, vertical: 16),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
