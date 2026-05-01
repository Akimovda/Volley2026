import UIKit
import WebKit

// MARK: - Model

struct VolleyNotification: Decodable {
    let id: Int
    let type: String
    let title: String
    let body: String
    let url: String?
    let readAt: String?
    let createdAt: String
    let createdAtHuman: String

    var isRead: Bool { readAt != nil }

    enum CodingKeys: String, CodingKey {
        case id, type, title, body, url
        case readAt = "read_at"
        case createdAt = "created_at"
        case createdAtHuman = "created_at_human"
    }
}

struct NotificationsPage: Decodable {
    let data: [VolleyNotification]
    let hasMore: Bool
    let nextPage: Int

    enum CodingKeys: String, CodingKey {
        case data
        case hasMore = "has_more"
        case nextPage = "next_page"
    }
}

// MARK: - ViewController

class NotificationsViewController: UIViewController {

    // Injected by plugin — used to extract session cookies from WKWebView
    weak var webView: WKWebView?

    private let tableView = UITableView(frame: .zero, style: .plain)
    private let refreshControl = UIRefreshControl()
    private let emptyLabel = UILabel()
    private let loadingIndicator = UIActivityIndicatorView(style: .medium)

    private var items: [VolleyNotification] = []
    private var currentPage = 1
    private var hasMore = false
    private var isLoading = false

    private let baseURL = "https://volleyplay.club"

    // MARK: - Lifecycle

    override func viewDidLoad() {
        super.viewDidLoad()
        setupUI()
        loadPage(page: 1, reset: true)
    }

    // MARK: - UI Setup

    private func setupUI() {
        title = "Уведомления"
        view.backgroundColor = .systemBackground

        navigationItem.rightBarButtonItem = UIBarButtonItem(
            title: "Прочитать все",
            style: .plain,
            target: self,
            action: #selector(markAllRead)
        )
        navigationItem.leftBarButtonItem = UIBarButtonItem(
            barButtonSystemItem: .close,
            target: self,
            action: #selector(close)
        )

        tableView.register(NotificationCell.self, forCellReuseIdentifier: NotificationCell.reuseId)
        tableView.dataSource = self
        tableView.delegate = self
        tableView.rowHeight = UITableView.automaticDimension
        tableView.estimatedRowHeight = 80
        tableView.separatorInset = UIEdgeInsets(top: 0, left: 16, bottom: 0, right: 0)
        tableView.refreshControl = refreshControl
        refreshControl.addTarget(self, action: #selector(pullToRefresh), for: .valueChanged)
        tableView.translatesAutoresizingMaskIntoConstraints = false
        view.addSubview(tableView)

        emptyLabel.text = "Нет уведомлений"
        emptyLabel.textAlignment = .center
        emptyLabel.textColor = .secondaryLabel
        emptyLabel.font = .systemFont(ofSize: 16)
        emptyLabel.isHidden = true
        emptyLabel.translatesAutoresizingMaskIntoConstraints = false
        view.addSubview(emptyLabel)

        loadingIndicator.hidesWhenStopped = true
        loadingIndicator.translatesAutoresizingMaskIntoConstraints = false
        view.addSubview(loadingIndicator)

        NSLayoutConstraint.activate([
            tableView.topAnchor.constraint(equalTo: view.topAnchor),
            tableView.leadingAnchor.constraint(equalTo: view.leadingAnchor),
            tableView.trailingAnchor.constraint(equalTo: view.trailingAnchor),
            tableView.bottomAnchor.constraint(equalTo: view.bottomAnchor),

            emptyLabel.centerXAnchor.constraint(equalTo: view.centerXAnchor),
            emptyLabel.centerYAnchor.constraint(equalTo: view.centerYAnchor),

            loadingIndicator.centerXAnchor.constraint(equalTo: view.centerXAnchor),
            loadingIndicator.centerYAnchor.constraint(equalTo: view.centerYAnchor),
        ])
    }

    // MARK: - Actions

    @objc private func close() {
        dismiss(animated: true)
    }

    @objc private func pullToRefresh() {
        loadPage(page: 1, reset: true)
    }

    @objc private func markAllRead() {
        apiRequest(path: "/api/notifications/read-all", method: "POST") { [weak self] _ in
            DispatchQueue.main.async {
                self?.items = self?.items.map { n in
                    guard !n.isRead else { return n }
                    // Re-decode with read_at filled — easier to just rebuild
                    return VolleyNotification(
                        id: n.id, type: n.type, title: n.title, body: n.body,
                        url: n.url, readAt: ISO8601DateFormatter().string(from: Date()),
                        createdAt: n.createdAt, createdAtHuman: n.createdAtHuman
                    )
                } ?? []
                self?.tableView.reloadData()
                self?.postBadgeUpdate()
            }
        }
    }

    // MARK: - Data Loading

    private func loadPage(page: Int, reset: Bool) {
        guard !isLoading else { return }
        isLoading = true
        if reset { loadingIndicator.startAnimating() }

        apiRequest(path: "/api/notifications?page=\(page)&per_page=20", method: "GET") { [weak self] result in
            DispatchQueue.main.async {
                guard let self = self else { return }
                self.isLoading = false
                self.loadingIndicator.stopAnimating()
                self.refreshControl.endRefreshing()

                switch result {
                case .success(let data):
                    guard let page = try? JSONDecoder().decode(NotificationsPage.self, from: data) else { return }
                    if reset {
                        self.items = page.data
                    } else {
                        self.items.append(contentsOf: page.data)
                    }
                    self.hasMore = page.hasMore
                    self.currentPage = page.nextPage
                    self.tableView.reloadData()
                    self.emptyLabel.isHidden = !self.items.isEmpty
                case .failure:
                    break
                }
            }
        }
    }

    // MARK: - API

    private func apiRequest(
        path: String,
        method: String,
        completion: @escaping (Result<Data, Error>) -> Void
    ) {
        guard let url = URL(string: baseURL + path) else { return }

        // Extract session cookies from WKWebView's isolated cookie store
        guard let wv = webView else {
            completion(.failure(URLError(.userAuthenticationRequired)))
            return
        }

        wv.configuration.websiteDataStore.httpCookieStore.getAllCookies { cookies in
            var request = URLRequest(url: url)
            request.httpMethod = method
            request.setValue("application/json", forHTTPHeaderField: "Accept")
            request.setValue("application/json", forHTTPHeaderField: "Content-Type")

            // Build Cookie header manually (WKWebView cookies are isolated from URLSession)
            let cookieHeader = cookies
                .filter { $0.domain.contains("volleyplay.club") }
                .map { "\($0.name)=\($0.value)" }
                .joined(separator: "; ")
            if !cookieHeader.isEmpty {
                request.setValue(cookieHeader, forHTTPHeaderField: "Cookie")
            }

            URLSession.shared.dataTask(with: request) { data, _, error in
                if let error = error {
                    completion(.failure(error))
                } else {
                    completion(.success(data ?? Data()))
                }
            }.resume()
        }
    }

    private func deleteNotification(id: Int, completion: @escaping (Bool) -> Void) {
        apiRequest(path: "/api/notifications/\(id)", method: "DELETE") { result in
            DispatchQueue.main.async {
                if case .success = result { completion(true) } else { completion(false) }
            }
        }
    }

    private func markRead(id: Int) {
        apiRequest(path: "/api/notifications/\(id)/read", method: "POST") { _ in }
    }

    private func postBadgeUpdate() {
        // Notify the web layer to refresh its badge count
        webView?.evaluateJavaScript(
            "if(window.VolleyNative && window.VolleyNative.updateBadge) { " +
            "fetch('/api/notifications/unread-count',{credentials:'same-origin',headers:{'Accept':'application/json'}})" +
            ".then(r=>r.json()).then(d=>window.VolleyNative.updateBadge(d.count||0)).catch(()=>{}); }",
            completionHandler: nil
        )
    }
}

// MARK: - UITableViewDataSource

extension NotificationsViewController: UITableViewDataSource {

    func tableView(_ tableView: UITableView, numberOfRowsInSection section: Int) -> Int {
        items.count
    }

    func tableView(_ tableView: UITableView, cellForRowAt indexPath: IndexPath) -> UITableViewCell {
        let cell = tableView.dequeueReusableCell(withIdentifier: NotificationCell.reuseId, for: indexPath) as! NotificationCell
        cell.configure(with: items[indexPath.row])
        return cell
    }
}

// MARK: - UITableViewDelegate

extension NotificationsViewController: UITableViewDelegate {

    func tableView(_ tableView: UITableView, didSelectRowAt indexPath: IndexPath) {
        tableView.deselectRow(at: indexPath, animated: true)
        let n = items[indexPath.row]

        if !n.isRead {
            markRead(id: n.id)
            items[indexPath.row] = VolleyNotification(
                id: n.id, type: n.type, title: n.title, body: n.body,
                url: n.url, readAt: ISO8601DateFormatter().string(from: Date()),
                createdAt: n.createdAt, createdAtHuman: n.createdAtHuman
            )
            tableView.reloadRows(at: [indexPath], with: .none)
            postBadgeUpdate()
        }

        if let path = n.url, !path.isEmpty, let url = URL(string: baseURL + path) {
            dismiss(animated: true) {
                // Navigate WebView to the URL via JS
                self.webView?.evaluateJavaScript("window.location.href = '\(url.absoluteString)';", completionHandler: nil)
            }
        }
    }

    // Swipe to delete
    func tableView(
        _ tableView: UITableView,
        trailingSwipeActionsConfigurationForRowAt indexPath: IndexPath
    ) -> UISwipeActionsConfiguration? {
        let action = UIContextualAction(style: .destructive, title: "Удалить") { [weak self] _, _, done in
            guard let self = self else { done(false); return }
            let n = self.items[indexPath.row]
            self.deleteNotification(id: n.id) { success in
                if success {
                    self.items.remove(at: indexPath.row)
                    tableView.deleteRows(at: [indexPath], with: .automatic)
                    self.emptyLabel.isHidden = !self.items.isEmpty
                }
                done(success)
            }
        }
        action.image = UIImage(systemName: "trash")
        return UISwipeActionsConfiguration(actions: [action])
    }

    // Infinite scroll
    func scrollViewDidScroll(_ scrollView: UIScrollView) {
        let threshold = scrollView.contentSize.height - scrollView.frame.height - 200
        guard scrollView.contentOffset.y > threshold, hasMore, !isLoading else { return }
        loadPage(page: currentPage, reset: false)
    }
}

// MARK: - Cell

class NotificationCell: UITableViewCell {

    static let reuseId = "NotificationCell"

    private let titleLabel = UILabel()
    private let bodyLabel = UILabel()
    private let timeLabel = UILabel()
    private let unreadDot = UIView()

    override init(style: UITableViewCell.CellStyle, reuseIdentifier: String?) {
        super.init(style: style, reuseIdentifier: reuseIdentifier)
        setupUI()
    }

    required init?(coder: NSCoder) { fatalError() }

    private func setupUI() {
        unreadDot.backgroundColor = .systemBlue
        unreadDot.layer.cornerRadius = 4
        unreadDot.translatesAutoresizingMaskIntoConstraints = false

        titleLabel.font = .systemFont(ofSize: 15, weight: .semibold)
        titleLabel.numberOfLines = 2
        titleLabel.translatesAutoresizingMaskIntoConstraints = false

        bodyLabel.font = .systemFont(ofSize: 13)
        bodyLabel.textColor = .secondaryLabel
        bodyLabel.numberOfLines = 3
        bodyLabel.translatesAutoresizingMaskIntoConstraints = false

        timeLabel.font = .systemFont(ofSize: 11)
        timeLabel.textColor = .tertiaryLabel
        timeLabel.translatesAutoresizingMaskIntoConstraints = false

        contentView.addSubview(unreadDot)
        contentView.addSubview(titleLabel)
        contentView.addSubview(bodyLabel)
        contentView.addSubview(timeLabel)

        NSLayoutConstraint.activate([
            unreadDot.widthAnchor.constraint(equalToConstant: 8),
            unreadDot.heightAnchor.constraint(equalToConstant: 8),
            unreadDot.leadingAnchor.constraint(equalTo: contentView.leadingAnchor, constant: 12),
            unreadDot.topAnchor.constraint(equalTo: titleLabel.topAnchor, constant: 6),

            titleLabel.leadingAnchor.constraint(equalTo: unreadDot.trailingAnchor, constant: 8),
            titleLabel.trailingAnchor.constraint(equalTo: timeLabel.leadingAnchor, constant: -8),
            titleLabel.topAnchor.constraint(equalTo: contentView.topAnchor, constant: 12),

            timeLabel.trailingAnchor.constraint(equalTo: contentView.trailingAnchor, constant: -16),
            timeLabel.topAnchor.constraint(equalTo: titleLabel.topAnchor),
            timeLabel.widthAnchor.constraint(lessThanOrEqualToConstant: 80),

            bodyLabel.leadingAnchor.constraint(equalTo: titleLabel.leadingAnchor),
            bodyLabel.trailingAnchor.constraint(equalTo: contentView.trailingAnchor, constant: -16),
            bodyLabel.topAnchor.constraint(equalTo: titleLabel.bottomAnchor, constant: 4),
            bodyLabel.bottomAnchor.constraint(equalTo: contentView.bottomAnchor, constant: -12),
        ])
    }

    func configure(with n: VolleyNotification) {
        titleLabel.text = n.title
        bodyLabel.text = n.body
        timeLabel.text = n.createdAtHuman
        unreadDot.isHidden = n.isRead
        titleLabel.textColor = n.isRead ? .secondaryLabel : .label
        backgroundColor = n.isRead ? .systemBackground : .systemBlue.withAlphaComponent(0.04)
    }
}
