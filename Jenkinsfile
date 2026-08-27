pipeline {
    agent any

    environment {
        NODE_ENV = 'test'
        APP_URL = 'http://localhost:3000'
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Install Dependencies') {
            steps {
                sh 'npm ci'
            }
        }

        stage('Lint & Format') {
            parallel {
                stage('TypeScript') {
                    steps {
                        sh 'npm run typecheck'
                    }
                }
                stage('ESLint') {
                    steps {
                        sh 'npm run lint'
                    }
                }
                stage('Prettier') {
                    steps {
                        sh 'npm run format:check'
                    }
                }
            }
        }

        stage('Unit Tests') {
            steps {
                sh 'npm run test -- --coverage'
            }
            post {
                always {
                    junit 'coverage/junit.xml'
                    publishHTML(target: [
                        allowMissing: false,
                        alwaysLinkToLastBuild: true,
                        keepAll: true,
                        reportDir: 'coverage',
                        reportFiles: 'index.html',
                        reportName: 'Coverage Report'
                    ])
                }
            }
        }

        stage('Build') {
            steps {
                sh 'npm run build'
            }
        }

        stage('E2E Tests') {
            steps {
                sh 'npx playwright install --with-deps chromium'
                sh 'npm run test:e2e'
            }
            post {
                always {
                    playwright {}
                }
            }
        }

        stage('Build Docker Image') {
            when {
                branch 'main'
            }
            steps {
                sh 'docker build -t nexi-erp-frontend:latest .'
                sh 'docker tag nexi-erp-frontend:latest registry.example.com/nexi-erp-frontend:${BUILD_NUMBER}'
                sh 'docker push registry.example.com/nexi-erp-frontend:${BUILD_NUMBER}'
            }
        }

        stage('Deploy to Kubernetes') {
            when {
                branch 'main'
            }
            steps {
                sh """
                    kubectl set image deployment/nexi-erp-frontend \
                        app=registry.example.com/nexi-erp-frontend:${BUILD_NUMBER} \
                        --record
                """
            }
        }
    }

    post {
        always {
            cleanWs()
        }
        failure {
            slackSend(
                color: '#FF0000',
                message: "Frontend pipeline failed: ${env.JOB_NAME} [${env.BUILD_NUMBER}]"
            )
        }
        success {
            slackSend(
                color: '#00FF00',
                message: "Frontend pipeline succeeded: ${env.JOB_NAME} [${env.BUILD_NUMBER}]"
            )
        }
    }
}
