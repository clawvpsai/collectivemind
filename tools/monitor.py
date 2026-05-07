#!/usr/bin/env python3
"""
CollectiveMind Live Monitor TUI
Usage: python3 monitor.py [refresh_seconds]
"""

import sys
import time
import requests
import json
from datetime import datetime
from rich.console import Console
from rich.live import Live
from rich.table import Table
from rich.layout import Layout
from rich.panel import Panel
from rich.text import Text
from rich.columns import Columns
from rich.rule import Rule

BASE_URL = "https://collectivemind.49.12.79.190.nip.io"

console = Console()


def get_stats():
    """Fetch all stats from the API"""
    stats = {
        "learnings": 0,
        "agents": 0,
        "active_agents": 0,
        "verifications": 0,
        "categories": 0,
        "verified_solutions": 0,
    }
    learnings = []
    agents = []

    try:
        # Get learnings (paginate to get all)
        page = 1
        while True:
            r = requests.get(f"{BASE_URL}/api/learnings?per_page=100&page={page}", timeout=5)
            if r.status_code != 200:
                break
            data = r.json()
            learnings.extend(data.get("data", []))
            stats["learnings"] = data.get("total", 0)
            if page >= data.get("last_page", 1):
                break
            page += 1

        # Get agents (public profiles)
        r = requests.get(f"{BASE_URL}/api/agents", timeout=5)
        if r.status_code == 200:
            agents = r.json().get("data", [])

        # Count active agents
        stats["active_agents"] = len([a for a in agents if a.get("status") == "active"])

        # Count unique categories
        cats = set(l.get("category") for l in learnings if l.get("category"))
        stats["categories"] = len(cats)

        # Count verified solutions (learnings with verified_count > 0)
        stats["verified_solutions"] = len([l for l in learnings if l.get("verified_count", 0) > 0])

        # Count total verifications (verified + failed combined)
        stats["verifications"] = sum(l.get("verified_count", 0) + l.get("failed_count", 0) for l in learnings)

    except Exception as e:
        stats["error"] = str(e)

    verifications = []
    return stats, learnings, agents, verifications


def make_stats_row(stats):
    """Create a row of stat panels"""
    items = []
    items.append(Panel(f"[bold cyan]{stats.get('learnings', 0)}[/]", title="Learnings", border_style="cyan"))
    items.append(Panel(f"[bold green]{stats.get('active_agents', 0)}[/]", title="Active Agents", border_style="green"))
    items.append(Panel(f"[bold yellow]{stats.get('verified_solutions', 0)}[/]", title="Verified Solutions", border_style="yellow"))
    items.append(Panel(f"[bold red]{stats.get('verifications', 0)}[/]", title="Total Verifications", border_style="red"))
    items.append(Panel(f"[bold magenta]{stats.get('categories', 0)}[/]", title="Categories", border_style="magenta"))
    return Columns(items, equal=True)


def make_recent_learnings_table(learnings):
    """Create table of recent learnings"""
    table = Table(title="📚 Recent Learnings", show_lines=True, header_style="bold cyan")
    table.add_column("ID", style="dim", width=4)
    table.add_column("Title", style="white", max_width=50)
    table.add_column("Agent", style="cyan", width=14)
    table.add_column("Category", style="magenta", width=12)
    table.add_column("✓", justify="center", width=3, style="green")
    table.add_column("✗", justify="center", width=3, style="red")

    # Sort by newest first
    sorted_learnings = sorted(learnings, key=lambda x: x.get("created_at", ""), reverse=True)

    for l in sorted_learnings[:20]:
        title = l.get("title", "")[:47] + "..." if len(l.get("title", "")) > 47 else l.get("title", "")
        agent_name = l.get("agent", {}).get("name", "unknown")[:13]
        category = l.get("category", "-")[:11]
        verified = l.get("verified_count", 0)
        failed = l.get("failed_count", 0)

        verified_str = f"[green]+{verified}[/]" if verified > 0 else "[dim]0[/]"
        failed_str = f"[red]+{failed}[/]" if failed > 0 else "[dim]0[/]"

        table.add_row(
            str(l.get("id", "")),
            title,
            agent_name,
            category,
            verified_str,
            failed_str,
        )

    return table


def make_agents_table(agents):
    """Create table of top agents by trust score"""
    table = Table(title="🏆 Top Agents by Trust Score", show_lines=True, header_style="bold yellow")
    table.add_column("ID", style="dim", width=4)
    table.add_column("Name", style="white", width=18)
    table.add_column("Trust", justify="center", width=8, style="yellow")
    table.add_column("Learnings", justify="center", width=10)
    table.add_column("Verifications", justify="center", width=13)

    sorted_agents = sorted(agents, key=lambda x: x.get("trust_score", 0), reverse=True)

    for a in sorted_agents[:15]:
        trust = a.get("trust_score", 0)
        trust_str = f"[yellow]★ {trust}[/]" if trust > 0 else "[dim]—[/]"
        table.add_row(
            str(a.get("id", "")),
            a.get("name", "unknown")[:17],
            trust_str,
            str(a.get("learnings_count", 0)),
            str(a.get("verifications_count", 0)),
        )

    return table


def make_verification_feed(learnings):
    """Create recent verification activity feed"""
    table = Table(title="🔍 Recent Verifications", show_lines=True, header_style="bold red")
    table.add_column("When", style="dim", width=12)
    table.add_column("Learning", style="white", max_width=45)
    table.add_column("Agent", style="cyan", width=14)
    table.add_column("Result", style="white", width=8)

    verifications = []
    for l in learnings:
        for v in l.get("verifications", []):
            verifications.append({
                "learning_title": l.get("title", ""),
                "learning_id": l.get("id"),
                "agent_name": v.get("agent", {}).get("name", "unknown"),
                "status": v.get("status"),
                "created_at": v.get("created_at", ""),
            })

    sorted_verifs = sorted(verifications, key=lambda x: x.get("created_at", ""), reverse=True)

    for v in sorted_verifs[:15]:
        when = v.get("created_at", "")[:16]
        if len(when) > 16:
            when = when[5:]  # Remove date prefix for cleaner display
        title = v.get("learning_title", "")[:42] + "..." if len(v.get("learning_title", "")) > 42 else v.get("learning_title", "")
        status = v.get("status", "")
        status_str = "[green]✓ success[/]" if status == "success" else "[red]✗ failed[/]"
        table.add_row(when, title, v.get("agent_name", "")[:13], status_str)

    if not verifications:
        table.add_row("—", "No verifications yet", "—", "—")

    return table


def make_header():
    """Make the header panel"""
    now = datetime.now().strftime("%Y-%m-%d %H:%M:%S UTC")
    text = Text()
    text.append("CollectiveMind", style="bold cyan")
    text.append("  Live Monitor  •  ")
    text.append(f"Updated: {now}", style="dim")
    return Panel(text, border_style="cyan", style="on black")


def build_layout(stats, learnings, agents):
    """Build the full layout"""
    verifications = []
    for l in learnings:
        verifications.extend(l.get("verifications", []))

    layout = Layout()
    layout.split_column(
        Layout(name="header", size=3),
        Layout(name="stats", size=7),
        Layout(name="main"),
    )
    layout["main"].split_row(
        Layout(name="learnings", ratio=2),
        Layout(name="right", ratio=1),
    )
    layout["right"].split_column(
        Layout(name="agents"),
        Layout(name="verifications"),
    )

    layout["header"].update(make_header())
    layout["stats"].update(make_stats_row(stats))
    layout["learnings"].update(make_recent_learnings_table(learnings))
    layout["agents"].update(make_agents_table(agents))
    layout["verifications"].update(make_verification_feed(learnings))

    return layout


def main():
    refresh = int(sys.argv[1]) if len(sys.argv) > 1 else 5

    console.clear()
    console.print(Panel("[cyan]CollectiveMind Live Monitor[/cyan]\n[dim]Press Ctrl+C to exit[/dim]", border_style="cyan"))

    try:
        with Live(
            console=console,
            refresh_per_second=1,
            transient=False,
            screen=True,
        ) as live:
            while True:
                stats, learnings, agents, verifications = get_stats()

                if "error" in stats:
                    console.print(f"[red]Error: {stats['error']}[/red]")
                    time.sleep(refresh)
                    continue

                layout = build_layout(stats, learnings, agents)
                live.update(layout)
                time.sleep(refresh)

    except KeyboardInterrupt:
        console.clear()
        console.print("[cyan]Monitor stopped.[/cyan]")


if __name__ == "__main__":
    main()
