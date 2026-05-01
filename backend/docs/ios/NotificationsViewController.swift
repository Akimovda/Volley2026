import UIKit
import WebKit

// MARK: - Models

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

// MARK: - ViewController

class NotificationsViewController: UIViewController {

    // Injected by plugin to extract WKWebView session cookies
    var webView: WKWebView?

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
            action: #selector(markAllReadTapped)
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

    @objc private func markAllReadTapped() {
        callApi(method: "POST", path: "/api/notifications/read-all") { [weak self] _ in
            guard let self = self else { return }
            self.items = self.items.map { n in
                guard !n.isRead else { return n }
                return VolleyNotification(
                    id: n.id, type: n.type, title: n.title, body: n.body,
                    url: n.url, readAt: ISO8601DateFormatter().string(from: Date()),
                    createdAt: n.createdAt, createdAtHuman: n.createdAtHuman
                )
            }
            self.tableView.reloadData()
            self.refreshBadge()
        }
    }

    // MARK: - Data Loading

    private func loadPage(page: Int, reset: Bool) {
        guard !isLoading else { return }
        isLoading = true
        if reset { loadingIndicator.startAnimating() }

        callApi(method: "GET", path: "/api/notifications?page=\(page)&per_page=20") { [weak self] json in
            guard let self = self else { return }
            self.isLoading = false
            self.loadingIndicator.stopAnimating()
            self.refreshControl.endRefreshing()

            guard let json = json,
                  let dataArray = json["data"] as? [[String: Any]] else {
                print("[Notifications] Failed to parse page response")
                return
            }

            let decoded = dataArray.compactMap { Self.decodeNotification($0) }
            if reset {
                self.items = decoded
            } else {
                self.items.append(contentsOf: decoded)
            }
            self.hasMore = json["has_more"] as? Bool ?? false
            self.currentPage = json["next_page"] as? Int ?? (page + 1)
            self.tableView.reloadData()
            self.emptyLabel.isHidden = !self.items.isEmpty
            print("[Notifications] received \(decoded.count) items (total: \(self.items.count))")
        }
    }

    // MARK: - API

    private func callApi(method: String, path: String, completion: @escaping ([String: Any]?) -> Void) {
        guard let url = URL(string: baseURL + path) else {
            completion(nil); return
        }

        guard let wv = webView else {
            print("[Notifications] webView is nil, falling back to HTTPCookieStorage")
            callApiFallback(method: method, url: url, completion: completion)
            return
        }

        wv.configuration.websiteDataStore.httpCookieStore.getAllCookies { [weak self] cookies in
            guard let self = self else { return }

            let domainCookies = cookies.filter {
                $0.domain.contains("volleyplay.club") || $0.domain.hasPrefix(".volleyplay.club")
            }
            let cookieHeader = domainCookies.map { "\($0.name)=\($0.value)" }.joined(separator: "; ")

            print("[Notifications] Cookies count: \(domainCookies.count)")
            print("[Notifications] Cookie header: \(cookieHeader.prefix(120))")

            var request = URLRequest(url: url)
            request.httpMethod = method
            request.setValue("application/json", forHTTPHeaderField: "Accept")
            request.setValue("application/json", forHTTPHeaderField: "Content-Type")
            // Laravel распознаёт AJAX запрос и возвращает JSON 401 вместо 302 redirect
            request.setValue("XMLHttpRequest", forHTTPHeaderField: "X-Requested-With")

            if !cookieHeader.isEmpty {
                request.setValue(cookieHeader, forHTTPHeaderField: "Cookie")
            }

            // CSRF токен для POST/DELETE (XSRF-TOKEN хранится в cookies, URL-encoded)
            if method != "GET", let csrfCookie = domainCookies.first(where: { $0.name == "XSRF-TOKEN" }) {
                let decoded = csrfCookie.value.removingPercentEncoding ?? csrfCookie.value
                request.setValue(decoded, forHTTPHeaderField: "X-XSRF-TOKEN")
            }

            URLSession.shared.dataTask(with: request) { data, response, error in
                DispatchQueue.main.async {
                    if let httpResp = response as? HTTPURLResponse {
                        print("[Notifications] \(method) \(path) → \(httpResp.statusCode)")
                    }
                    guard let data = data else {
                        print("[Notifications] No data, error: \(error?.localizedDescription ?? "nil")")
                        completion(nil); return
                    }
                    self.parseResponse(data: data, completion: completion)
                }
            }.resume()
        }
    }

    private func callApiFallback(method: String, url: URL, completion: @escaping ([String: Any]?) -> Void) {
        let cookies = HTTPCookieStorage.shared.cookies(for: url) ?? []
        let cookieHeader = cookies.map { "\($0.name)=\($0.value)" }.joined(separator: "; ")

        var request = URLRequest(url: url)
        request.httpMethod = method
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.setValue("XMLHttpRequest", forHTTPHeaderField: "X-Requested-With")
        if !cookieHeader.isEmpty {
            request.setValue(cookieHeader, forHTTPHeaderField: "Cookie")
        }

        URLSession.shared.dataTask(with: request) { [weak self] data, response, _ in
            DispatchQueue.main.async {
                if let httpResp = response as? HTTPURLResponse {
                    print("[Notifications] fallback → \(httpResp.statusCode)")
                }
                guard let data = data else { completion(nil); return }
                self?.parseResponse(data: data, completion: completion)
            }
        }.resume()
    }

    private func parseResponse(data: Data, completion: ([String: Any]?) -> Void) {
        if let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any] {
            completion(json)
        } else if let array = try? JSONSerialization.jsonObject(with: data) as? [[String: Any]] {
            completion(["data": array])
        } else {
            let preview = String(data: data, encoding: .utf8).map { String($0.prefix(200)) } ?? "binary"
            print("[Notifications] Bad JSON: \(preview)")
            completion(nil)
        }
    }

    // MARK: - Badge

    private func refreshBadge() {
        webView?.evaluateJavaScript(
            "fetch('/api/notifications/unread-count',{credentials:'same-origin'," +
            "headers:{'Accept':'application/json'}})" +
            ".then(r=>r.json()).then(d=>{if(window.VolleyNative)window.VolleyNative.updateBadge(d.count||0);})" +
            ".catch(()=>{});",
            completionHandler: nil
        )
    }

    // MARK: - Helpers

    private static func decodeNotification(_ d: [String: Any]) -> VolleyNotification? {
        guard let id = d["id"] as? Int,
              let type = d["type"] as? String,
              let title = d["title"] as? String,
              let body = d["body"] as? String,
              let createdAt = d["created_at"] as? String,
              let createdAtHuman = d["created_at_human"] as? String
        else { return nil }

        return VolleyNotification(
            id: id, type: type, title: title, body: body,
            url: d["url"] as? String,
            readAt: d["read_at"] as? String,
            createdAt: createdAt,
            createdAtHuman: createdAtHuman
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
            callApi(method: "POST", path: "/api/notifications/\(n.id)/read") { _ in }
            items[indexPath.row] = VolleyNotification(
                id: n.id, type: n.type, title: n.title, body: n.body,
                url: n.url, readAt: ISO8601DateFormatter().string(from: Date()),
                createdAt: n.createdAt, createdAtHuman: n.createdAtHuman
            )
            tableView.reloadRows(at: [indexPath], with: .none)
            refreshBadge()
        }

        if let path = n.url, !path.isEmpty, let url = URL(string: baseURL + path) {
            dismiss(animated: true) { [weak self] in
                self?.webView?.evaluateJavaScript(
                    "window.location.href = '\(url.absoluteString)';",
                    completionHandler: nil
                )
            }
        }
    }

    func tableView(
        _ tableView: UITableView,
        trailingSwipeActionsConfigurationForRowAt indexPath: IndexPath
    ) -> UISwipeActionsConfiguration? {
        let action = UIContextualAction(style: .destructive, title: "Удалить") { [weak self] _, _, done in
            guard let self = self else { done(false); return }
            let n = self.items[indexPath.row]
            self.callApi(method: "DELETE", path: "/api/notifications/\(n.id)") { json in
                let ok = json?["ok"] as? Bool ?? false
                if ok {
                    self.items.remove(at: indexPath.row)
                    tableView.deleteRows(at: [indexPath], with: .automatic)
                    self.emptyLabel.isHidden = !self.items.isEmpty
                }
                done(ok)
            }
        }
        action.image = UIImage(systemName: "trash")
        return UISwipeActionsConfiguration(actions: [action])
    }

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
