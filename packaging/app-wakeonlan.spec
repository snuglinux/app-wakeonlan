Name:           app-wakeonlan
Version:        0.0.1
Release:        2%{?dist}
Summary:        ClearOS Wake-on-LAN web interface

License:        GPLv3
URL:            https://github.com/snuglinux/app-wakeonlan
Source0:        https://github.com/snuglinux/app-wakeonlan/archive/refs/tags/%{version}.tar.gz

BuildArch:      noarch
Requires:       app-base
Requires:       wol
Requires:       iproute

%description
WakeOnLan for ClearOS provides a simple web interface for managing network
hosts and sending Wake-on-LAN magic packets through a selected local interface.

Features include device management, MAC/IP storage, online status checks,
local bind IP selection, optional broadcast IP, and Ukrainian/English language
support.

%prep
%setup -q -n %{name}-%{version}

%build
# Nothing to build.

%install
rm -rf %{buildroot}

install -d -m 0755 %{buildroot}/usr/clearos/apps/wakeonlan

# Expected repository layout:
#   app-wakeonlan-0.0.1/wakeonlan/...
# Fallback supports archives where app files are placed directly in root.
if [ -d wakeonlan ]; then
    cp -a wakeonlan/. %{buildroot}/usr/clearos/apps/wakeonlan/
elif [ -d controllers ] && [ -d libraries ] && [ -d views ] && [ -d deploy ]; then
    cp -a controllers libraries views deploy %{buildroot}/usr/clearos/apps/wakeonlan/
    [ -d htdocs ] && cp -a htdocs %{buildroot}/usr/clearos/apps/wakeonlan/
    [ -d language ] && cp -a language %{buildroot}/usr/clearos/apps/wakeonlan/
else
    echo "ERROR: Cannot find ClearOS app source layout." >&2
    exit 1
fi

%files
%defattr(-,root,root,-)
/usr/clearos/apps/wakeonlan
%ghost %config(noreplace) /etc/clearos/wakeonlan.conf

%changelog
* Mon May 11 2026 SnugLinux <snuglinux@users.noreply.github.com> - 0.0.1-2
- Remove direct php dependency to avoid pulling Apache httpd packages.

* Mon May 11 2026 SnugLinux <snuglinux@users.noreply.github.com> - 0.0.1-1
- Initial ClearOS WakeOnLan package.
