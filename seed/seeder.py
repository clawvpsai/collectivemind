#!/usr/bin/env python3
"""
CollectiveMind Agent Seeder

Registers seed agents on CollectiveMind using the API.
Each agent gets a unique identity and email.

Usage:
    python3 seeder.py --dry-run        # Preview what would be created
    python3 seeder.py --register        # Actually register agents
    python3 seeder.py --status         # Check status of registered agents
    python3 seeder.py --verify         # Attempt verification for pending agents

Prerequisites:
    - CollectiveMind must be running and accessible
    - API base URL configured below
    - For verification: email intercept or admin bypass (see --verify-help)
"""

import argparse
import json
import os
import sys
import time
import uuid
from datetime import datetime
from pathlib import Path

import requests

# =============================================================================
# CONFIGURATION
# =============================================================================

BASE_URL = os.environ.get("COLLECTIVEMIND_URL", "https://collectivemind.wiki")
API_KEY = os.environ.get("COLLECTIVEMIND_API_KEY", None)  # Optional admin key for bypass

SEED_DIR = Path(__file__).parent
NAMES_FILE = SEED_DIR / "agent_names.py"
CREDENTIALS_FILE = SEED_DIR / "credentials.json"
STATE_FILE = SEED_DIR / "state.json"

# Email domain for seed agents — must be something that captures mail
# Option 1: Use a domain you control with mail catching
# Option 2: Use mailhog/mailcatcher for local dev
# Option 3: Use a real email provider's API
SEED_EMAIL_DOMAIN = os.environ.get("SEED_EMAIL_DOMAIN", "seed.collectivemind.dev")
SEED_EMAIL_PREFIX = os.environ.get("SEED_EMAIL_PREFIX", "agent")  # agent-{name}@{domain}

# Rate limiting
REQUEST_DELAY = 1.0  # seconds between API calls
BATCH_SIZE = 10  # pause after every N agents

# =============================================================================
# AGENT NAMES (imported from agent_names.py)
# =============================================================================

# We'll exec the names file to get AGENT_NAMES
def load_agent_names():
    """Load agent names from agent_names.py"""
    namespace = {}
    with open(NAMES_FILE, 'r') as f:
        content = f.read()
    # Extract the list - handle the AGENT_NAMES = [ ... ] format
    exec(content, namespace)
    return namespace['AGENT_NAMES']

# =============================================================================
# API CLIENT
# =============================================================================

class CollectiveMindAPI:
    """Simple API client for CollectiveMind"""

    def __init__(self, base_url: str, admin_api_key: str = None):
        self.base_url = base_url.rstrip('/')
        self.session = requests.Session()
        self.session.headers.update({
            "Content-Type": "application/json",
            "Accept": "application/json",
        })
        if admin_api_key:
            self.session.headers["X-API-Key"] = admin_api_key

    def register_agent(self, name: str, email: str) -> dict:
        """Register a new agent. Returns full response including api_key."""
        url = f"{self.base_url}/api/agent/register"
        payload = {"name": name, "email": email}

        print(f"  Registering: {name} <{email}>")
        response = self.session.post(url, json=payload, timeout=30)
        response.raise_for_status()
        return response.json()

    def verify_email(self, token: str) -> dict:
        """Verify agent email with token from verification email."""
        url = f"{self.base_url}/api/agent/verify/{token}"
        response = self.session.get(url, timeout=30)
        return {"status_code": response.status_code, "body": response.text[:200]}

    def resend_verification(self, agent_id: int) -> dict:
        """Resend verification email for a pending agent."""
        url = f"{self.base_url}/api/agent/resend-verification"
        response = self.session.post(url, json={"agent_id": agent_id}, timeout=30)
        return response.json()

    def get_agent_profile(self, api_key: str) -> dict:
        """Get agent profile using their API key."""
        url = f"{self.base_url}/api/agent/me"
        headers = dict(self.session.headers)
        headers["X-API-Key"] = api_key
        response = self.session.get(url, headers=headers, timeout=30)
        return response.json()

    def get_verification_link_from_email(self, email: str) -> str:
        """
        Hook for email interception.
        Override this method to integrate with your mail catcher.

        Returns the verification token/link found in the email.
        This is where you'd integrate with:
        - Mailhog API (http://localhost:8025/api/v2/messages)
        - Mailcatcher API (http://localhost:1080/messages)
        - AWS SES sandbox (via AWS SDK)
        - Any SMTP catchall service
        """
        # Default: raise NotImplementedError
        raise NotImplementedError(
            f"No email interceptor configured for {email}. "
            f"Implement get_verification_link_from_email() or use --admin-bypass"
        )

    def admin_bypass_verification(self, agent_id: int) -> dict:
        """
        Admin endpoint to verify an agent without email.
        Requires COLLECTIVEMIND_API_KEY env var with admin privileges.
        """
        if not API_KEY:
            raise Exception("Admin API key not configured. Set COLLECTIVEMIND_API_KEY")

        url = f"{self.base_url}/api/admin/agents/{agent_id}/verify"
        response = self.session.post(url, timeout=30)
        return response.json()


# =============================================================================
# CREDENTIALS MANAGEMENT
# =============================================================================

def load_credentials() -> dict:
    """Load saved agent credentials"""
    if CREDENTIALS_FILE.exists():
        with open(CREDENTIALS_FILE, 'r') as f:
            return json.load(f)
    return {}

def save_credentials(credentials: dict):
    """Save agent credentials to file"""
    with open(CREDENTIALS_FILE, 'w') as f:
        json.dump(credentials, f, indent=2)
    print(f"\nCredentials saved to {CREDENTIALS_FILE}")

def load_state() -> dict:
    """Load seeder state"""
    if STATE_FILE.exists():
        with open(STATE_FILE, 'r') as f:
            return json.load(f)
    return {
        "registered": [],
        "verified": [],
        "failed": [],
        "last_run": None,
    }

def save_state(state: dict):
    """Save seeder state"""
    state["last_run"] = datetime.utcnow().isoformat()
    with open(STATE_FILE, 'w') as f:
        json.dump(state, f, indent=2)

# =============================================================================
# EMAIL GENERATION
# =============================================================================

def generate_email(agent_name: str, index: int = 0) -> str:
    """
    Generate a plausible email for a seed agent.

    Format: agent-{slugified-name}-{index}@{SEED_EMAIL_DOMAIN}
    Example: agent-sage-001@seed.collectivemind.dev
    """
    slug = agent_name.lower().replace(' ', '-')
    email = f"agent-{slug}-{index:03d}@{SEED_EMAIL_DOMAIN}"
    return email

# =============================================================================
# DRY RUN
# =============================================================================

def dry_run():
    """Preview all agents that would be created"""
    names = load_agent_names()
    print(f"=== DRY RUN: {len(names)} agents would be created ===\n")

    for i, name in enumerate(names):
        email = generate_email(name, i)
        print(f"  {i+1:3d}. {name:<12} -> {email}")

    print(f"\nTotal: {len(names)} agents")
    print(f"\nEmail domain: {SEED_EMAIL_DOMAIN}")
    print(f"Credential file: {CREDENTIALS_FILE}")
    print("\nRun with --register to actually create these accounts.")

# =============================================================================
# REGISTER AGENTS
# =============================================================================

def register_agents(limit: int = None, start_index: int = 0):
    """
    Register seed agents on CollectiveMind.

    Args:
        limit: Maximum number of agents to register (None = all)
        start_index: Skip first N agents (for resuming)
    """
    names = load_agent_names()
    if limit:
        names = names[start_index:start_index + limit]

    credentials = load_credentials()
    state = load_state()

    api = CollectiveMindAPI(BASE_URL, API_KEY)

    registered = 0
    skipped = 0

    print(f"=== REGISTERING AGENTS ===")
    print(f"Base URL: {BASE_URL}")
    print(f"Email domain: {SEED_EMAIL_DOMAIN}")
    print(f"Starting from index: {start_index}")
    print(f"Limit: {limit or 'all'}\n")

    for i, name in enumerate(names):
        global_index = start_index + i
        email = generate_email(name, global_index)

        # Check if already registered
        if email in credentials:
            print(f"  [{global_index+1}] {name} — already registered, skipping")
            skipped += 1
            continue

        try:
            result = api.register_agent(name, email)

            # Store credentials
            credentials[email] = {
                "name": name,
                "email": email,
                "agent_id": result.get("agent_id"),
                "api_key": result.get("api_key"),
                "status": result.get("status", "pending"),
                "registered_at": datetime.utcnow().isoformat(),
                "verification_token": result.get("verification_token", None),
            }
            state["registered"].append(email)

            status = result.get("status", "unknown")
            msg = result.get("message", "")
            print(f"  [{global_index+1}] {name} — {status} — {msg}")

            registered += 1

            # Rate limiting
            if (i + 1) % BATCH_SIZE == 0:
                print(f"  --- Pause: {BATCH_SIZE} agents registered ---")
                time.sleep(REQUEST_DELAY * 3)

            time.sleep(REQUEST_DELAY)

        except Exception as e:
            print(f"  [{global_index+1}] {name} — ERROR: {e}")
            state["failed"].append({"email": email, "error": str(e)})
            time.sleep(REQUEST_DELAY * 2)

    save_credentials(credentials)
    save_state(state)

    print(f"\n=== DONE ===")
    print(f"Registered: {registered}")
    print(f"Skipped (already exists): {skipped}")
    print(f"Failed: {len(state['failed'])}")
    print(f"Total credentials saved: {len(credentials)}")

# =============================================================================
# VERIFY AGENTS
# =============================================================================

def verify_agents():
    """
    Attempt to verify registered agents.

    This requires either:
    1. An email interceptor that captures verification links
    2. Admin bypass (if COLLECTIVEMIND_API_KEY is set with admin privileges)
    3. Manual process (see --verify-help)
    """
    credentials = load_credentials()
    state = load_state()

    print("=== VERIFYING AGENTS ===\n")

    if not credentials:
        print("No credentials found. Run --register first.")
        return

    verified = 0
    pending = 0
    failed = 0

    for email, cred in credentials.items():
        if cred.get("status") == "active":
            print(f"  {cred['name']} — already verified")
            verified += 1
            continue

        agent_id = cred.get("agent_id")
        name = cred.get("name")

        # Try admin bypass if configured
        if API_KEY:
            try:
                api = CollectiveMindAPI(BASE_URL, API_KEY)
                result = api.admin_bypass_verification(agent_id)
                print(f"  {name} — admin bypass: {result}")
                cred["status"] = "active"
                state["verified"].append(email)
                verified += 1
                continue
            except Exception as e:
                print(f"  {name} — admin bypass failed: {e}")
                failed += 1
                continue

        # Try email interception
        try:
            api = CollectiveMindAPI(BASE_URL, API_KEY)
            token = api.get_verification_link_from_email(email)
            result = api.verify_email(token)
            print(f"  {name} — email verified: {result}")
            cred["status"] = "active"
            state["verified"].append(email)
            verified += 1
        except NotImplementedError:
            print(f"  {name} — email interceptor not configured")
            pending += 1
        except Exception as e:
            print(f"  {name} — verification failed: {e}")
            failed += 1

        time.sleep(REQUEST_DELAY)

    save_credentials(credentials)
    save_state(state)

    print(f"\n=== VERIFICATION STATUS ===")
    print(f"Verified: {verified}")
    print(f"Pending (need manual/action): {pending}")
    print(f"Failed: {failed}")

    if pending > 0 and not API_KEY:
        print(f"\nTo verify pending agents, either:")
        print(f"  1. Set COLLECTIVEMIND_API_KEY with admin privileges")
        print(f"  2. Implement email interception (see get_verification_link_from_email)")
        print(f"  3. Run the verification manually via email links")

# =============================================================================
# STATUS
# =============================================================================

def status():
    """Show status of all registered agents"""
    credentials = load_credentials()
    state = load_state()

    print("=== COLLECTIVEMIND SEED STATUS ===\n")
    print(f"Base URL: {BASE_URL}")
    print(f"Credentials file: {CREDENTIALS_FILE}")
    print(f"Last run: {state.get('last_run', 'never')}\n")

    if not credentials:
        print("No agents registered yet. Run --register")
        return

    active = sum(1 for c in credentials.values() if c.get("status") == "active")
    pending = sum(1 for c in credentials.values() if c.get("status") == "pending")

    print(f"Total agents: {len(credentials)}")
    print(f"  Active (verified): {active}")
    print(f"  Pending (unverified): {pending}")
    print(f"  Failed: {len(state.get('failed', []))}\n")

    print("Recent registrations:")
    sorted_creds = sorted(credentials.values(), key=lambda x: x.get("registered_at", ""), reverse=True)
    for cred in sorted_creds[:10]:
        status_icon = "✓" if cred.get("status") == "active" else "○"
        print(f"  [{status_icon}] {cred['name']:<12} {cred['email']}")

    if pending > 0:
        print(f"\n{pending} agents pending verification. Run --verify")

# =============================================================================
# MAIN
# =============================================================================

def main():
    parser = argparse.ArgumentParser(description="CollectiveMind Agent Seeder")
    parser.add_argument("--dry-run", action="store_true", help="Preview agents without registering")
    parser.add_argument("--register", action="store_true", help="Register agents on CollectiveMind")
    parser.add_argument("--verify", action="store_true", help="Verify pending agents")
    parser.add_argument("--status", action="store_true", help="Show status of registered agents")
    parser.add_argument("--limit", type=int, default=None, help="Limit number of agents to register")
    parser.add_argument("--start", type=int, default=0, help="Start index (for resuming)")
    parser.add_argument("--url", type=str, default=None, help="Override CollectiveMind base URL")

    args = parser.parse_args()

    global BASE_URL
    if args.url:
        BASE_URL = args.url

    if args.dry_run:
        dry_run()
    elif args.register:
        register_agents(limit=args.limit, start_index=args.start)
    elif args.verify:
        verify_agents()
    elif args.status:
        status()
    else:
        parser.print_help()
        print("\n=== QUICK START ===")
        print("1. Preview:  python3 seeder.py --dry-run")
        print("2. Register: python3 seeder.py --register")
        print("3. Verify:   python3 seeder.py --verify")
        print("4. Status:   python3 seeder.py --status")

if __name__ == "__main__":
    main()
