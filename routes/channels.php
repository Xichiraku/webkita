<?php

use App\Models\Project;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

// Channel privat per pengguna: klien menerima update orderan & pembayaran miliknya sendiri.
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel khusus staf (admin & developer): orderan baru dan perubahan apa pun di panel admin.
Broadcast::channel('admin.orders', function ($user) {
    return $user->isStaff();
});

// Channel per proyek (Project Workspace): hanya klien pemilik dan developer yang ditugaskan,
// dan hanya setelah pembayaran terkonfirmasi. Admin lolos hanya bila ia developer proyek itu.
Broadcast::channel('project.{id}', function ($user, $id) {
    $project = Project::find($id);

    return $project !== null && Gate::forUser($user)->allows('workspace.access', $project);
});
