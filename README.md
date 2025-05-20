
## Prerequisites

*   PHP >= 7.4
*   PHP `sqlite3` extension
*   PHP `json` extension (for JSON/REST API)
*   PHP `soap` extension (for SOAP API)
*   Composer (recommended for autoloading)
*   A web server (Apache, Nginx, or PHP's built-in server)

## Installation & Deployment

Detailed installation and deployment instructions are provided within the documentation for each API type:

*   **For JSON/REST API:** Please refer to [JSON/REST API Deployment Guide](./sqlite_json_handler/DOCUMENTATION_REST.md#deployment-steps).
*   **For SOAP API:** Please refer to [SOAP API Deployment Guide](./sqlite_soap_handler/DOCUMENTATION_SOAP.md#deployment-steps).

**General Steps:**

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/your-username/your-repo-name.git
    cd your-repo-name
    ```
2.  **Navigate to the desired API handler directory:**
    ```bash
    cd sqlite_json_handler  # or cd sqlite_soap_handler
    ```
3.  **Install dependencies:**
    ```bash
    composer install
    ```
4.  **Set Permissions:** Ensure the `databases/` and `backups/` directories within the chosen handler's path are writable by the web server.
    ```bash
    # Inside sqlite_json_handler or sqlite_soap_handler
    mkdir -p databases backups
    sudo chmod -R 775 databases backups
    # Adjust ownership if necessary, e.g., sudo chown -R www-data:www-data databases backups
    ```
5.  **Configure Web Server:** Set up your web server to point its document root to the `public/` directory within the chosen handler's path (e.g., `sqlite_json_handler/public/`).
6.  **(SOAP Only)** Configure the WSDL: Update the `<soap:address location="...">` in `public/database_service.wsdl` to point to your correct `soap_server.php` URL.

## Usage

Usage instructions, including request/response formats and code samples for interacting with the APIs, are available in their respective documentation files:

*   **JSON/REST API:** [JSON/REST API Usage Guide](./sqlite_json_handler/DOCUMENTATION_REST.md#api-operations-and-usage-requestresponse-samples)
*   **SOAP API:** [SOAP API Usage Guide](./sqlite_soap_handler/DOCUMENTATION_SOAP.md#api-operations-and-usage-client-side-examples)

## Contributing

Contributions are welcome! If you'd like to contribute, please follow these steps:

1.  Fork the repository.
2.  Create a new branch (`git checkout -b feature/your-feature-name`).
3.  Make your changes.
4.  Commit your changes (`git commit -m 'Add some feature'`).
5.  Push to the branch (`git push origin feature/your-feature-name`).
6.  Open a Pull Request.

Please ensure your code adheres to PSR standards where applicable and includes appropriate tests if new functionality is added.

## Support iQuipe Digital

If you find this project useful and would like to support its development, please consider making a donation. Your support helps maintain and improve this project, as well as develop new open-source tools.

*   **Donate via PayPal:** [https://www.paypal.me/YourPayPalUsername](https://www.paypal.me/YourPayPalUsername) *(Replace `YourPayPalUsername` with your actual PayPal.me link or another preferred donation link)*
*   **Donate via Buy Me A Coffee:** [https://www.buymeacoffee.com/YourBMCPage](https://www.buymeacoffee.com/YourBMCPage) *(Replace `YourBMCPage` or add other platforms if you prefer)*

Every contribution, no matter the size, is highly appreciated!

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details (you'll need to create this file).

---

*We hope this SQLite API Handler streamlines your database interactions!*
*For any inquiries or issues, please contact iQuipe Digital at [iqcloud@outlook.com](mailto:iqcloud@outlook.com).*