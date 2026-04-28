@once
    <style>
        .fieldops-table-wrap {
            overflow-x: auto;
        }

        .fieldops-table {
            width: 100%;
            min-width: 720px;
            border-collapse: collapse;
            font-size: 0.875rem;
            line-height: 1.25rem;
        }

        .fieldops-table th,
        .fieldops-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }

        .fieldops-table th {
            border-bottom: 1px solid rgba(148, 163, 184, 0.35);
            color: rgb(100, 116, 139);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.025em;
            text-transform: uppercase;
        }

        .fieldops-table tbody tr + tr td {
            border-top: 1px solid rgba(148, 163, 184, 0.18);
        }

        .fieldops-table td {
            color: rgb(51, 65, 85);
        }

        .fieldops-table .fieldops-table__primary {
            color: rgb(15, 23, 42);
            font-weight: 600;
        }

        .fieldops-table .fieldops-table__empty {
            padding: 1.5rem 1rem;
            text-align: center;
            color: rgb(100, 116, 139);
        }

        .fieldops-table a {
            color: rgb(2, 132, 199);
            font-weight: 500;
            text-decoration: none;
        }

        .fieldops-table a:hover {
            text-decoration: underline;
        }

        @media (prefers-color-scheme: dark) {
            .fieldops-table th {
                border-bottom-color: rgba(255, 255, 255, 0.1);
                color: rgb(156, 163, 175);
            }

            .fieldops-table tbody tr + tr td {
                border-top-color: rgba(255, 255, 255, 0.1);
            }

            .fieldops-table td {
                color: rgb(209, 213, 219);
            }

            .fieldops-table .fieldops-table__primary {
                color: rgb(255, 255, 255);
            }

            .fieldops-table .fieldops-table__empty {
                color: rgb(156, 163, 175);
            }

            .fieldops-table a {
                color: rgb(56, 189, 248);
            }
        }
    </style>
@endonce
