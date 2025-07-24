# Page snapshot

```yaml
- heading "Log In" [level=1]
- link "Powered by WordPress":
  - /url: https://wordpress.org/
- paragraph:
  - strong: "Error:"
  - text: The username
  - strong: admin
  - text: is not registered on this site. If you are unsure of your username, try your email address instead.
- paragraph:
  - text: Username or Email Address
  - textbox "Username or Email Address"
- text: Password
- textbox "Password"
- button "Show password"
- paragraph:
  - checkbox "Remember Me"
  - text: Remember Me
- paragraph:
  - button "Log In"
- paragraph:
  - link "Lost your password?":
    - /url: http://localhost:10008/wp-login.php?action=lostpassword
- paragraph:
  - link "← Go to Handy Seafood":
    - /url: http://localhost:10008/
```