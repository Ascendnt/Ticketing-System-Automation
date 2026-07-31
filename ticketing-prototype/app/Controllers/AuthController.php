<?php

namespace App\Controllers;

use App\Models\UserModel;

class AuthController extends BaseController
{
    protected UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function loginForm()
    {
        return view('auth/login');
    }

    public function login()
    {
        $email    = trim((string) $this->request->getPost('email'));
        $password = (string) $this->request->getPost('password');

        $user = $this->userModel->findByEmail($email);

        if ($user === null || ! $this->userModel->verifyPassword($user, $password)) {
            return redirect()->back()->withInput()->with('error', 'Invalid email or password.');
        }

        if ((int) $user['is_active'] !== 1) {
            return redirect()->back()->withInput()->with('error', 'This account has been deactivated. Contact your administrator.');
        }

        session()->set([
            'isLoggedIn' => true,
            'user' => [
                'id'         => $user['id'],
                'name'       => $user['name'],
                'email'      => $user['email'],
                'role'       => $user['role'],
                'department' => $user['department'],
            ],
        ]);

        return redirect()->to('/tickets')->with('success', 'Welcome back, ' . $user['name'] . '.');
    }

    public function logout()
    {
        session()->destroy();

        return redirect()->to('/login')->with('success', 'You have been logged out.');
    }

    public function signupForm()
    {
        return view('auth/signup');
    }

    public function signup()
    {
        $name     = trim((string) $this->request->getPost('name'));
        $email    = trim((string) $this->request->getPost('email'));
        $password = (string) $this->request->getPost('password');
        $confirm  = (string) $this->request->getPost('password_confirm');

        if ($name === '' || $email === '' || $password === '') {
            return redirect()->back()->withInput()->with('error', 'Please fill in all fields.');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->withInput()->with('error', 'Please enter a valid email address.');
        }

        if (strlen($password) < 8) {
            return redirect()->back()->withInput()->with('error', 'Password must be at least 8 characters.');
        }

        if ($password !== $confirm) {
            return redirect()->back()->withInput()->with('error', 'Passwords do not match.');
        }

        if ($this->userModel->findByEmail($email) !== null) {
            return redirect()->back()->withInput()->with('error', 'An account with that email already exists.');
        }

        $this->userModel->createAccount($name, $email, $password, UserModel::ROLE_REQUESTER);

        return redirect()->to('/login')->with('success', 'Account created. You can now log in.');
    }
}
