# PCA Membership plugin

WordPress plugin for pestcontrolassociation.in (v0.1.0).

- **PCA Members**: committee, region committee and member companies. Status active/left. Shortcodes: `[pca_members kind="committee|region|member"]`.
- **Applications**: join-form (Forminator form 810) submissions with automatic checks and one-click approve, which issues a numbered certificate and emails the link.
- **Certificates**: `/?pca_cert=PCA-YYYY-NNNN` (printable, with QR). Public check page: `[pca_verify]`.
- Passwords from the join form are never stored (text-4 / text-5 are blocked).

Install: zip the `pca-membership` folder and upload via Plugins > Add New > Upload.
Settings: PCA Members > Settings (form ID, field map).
